<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPatientMailJob;
use App\Jobs\WaitlistPromotedJob;
use App\Models\AccessToken;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\TreatmentType;
use App\Models\Waitlist;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if (! $actor) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $query = Appointment::query()
            ->with(['patient', 'slot.therapist', 'updatedByStaff'])
            ->latest();

        if ($role === 'patient') {
            $query->where('patient_id', $actor->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'patient' || ! $actor instanceof Patient) {
            return response()->json(['message' => '患者ログインが必要です。'], 403);
        }

        if (! $actor->canBookRehab()) {
            return response()->json(['message' => '初診診断前のため、リハビリ予約はできません。'], 422);
        }

        $data = $request->validate([
            'appointment_slot_id' => ['nullable', 'integer', 'exists:appointment_slots,id'],
            'therapist_id' => ['required_without:appointment_slot_id', 'integer', 'exists:therapists,id'],
            'date' => ['required_without:appointment_slot_id', 'date_format:Y-m-d'],
            'time' => ['required_without:appointment_slot_id', 'date_format:H:i'],
        ]);

        $slot = isset($data['appointment_slot_id'])
            ? AppointmentSlot::query()->with('appointments')->findOrFail($data['appointment_slot_id'])
            : AppointmentSlot::query()->firstOrCreate(
                [
                    'therapist_id' => $data['therapist_id'],
                    'date' => $data['date'],
                    'starts_at' => $data['time'].':00',
                ],
                [
                    'ends_at' => CarbonImmutable::createFromFormat('H:i', $data['time'])->addMinutes(30)->format('H:i:s'),
                    'capacity' => 1,
                ],
            )->load('appointments');

        if (! $slot->is_available) {
            return response()->json(['message' => 'この枠は満枠です。'], 422);
        }

        $appointment = Appointment::query()->firstOrNew([
            'patient_id' => $actor->id,
            'appointment_slot_id' => $slot->id,
        ]);

        $appointment->status = Appointment::STATUS_BOOKED;
        $appointment->staff_id ??= Staff::query()->orderBy('id')->value('id');
        $appointment->treatment_type_id ??= TreatmentType::query()->orderBy('id')->value('id');
        $appointment->save();

        SendPatientMailJob::dispatch(SendPatientMailJob::TYPE_RESERVATION_CONFIRMED, $appointment->id);

        return response()->json([
            'message' => '予約を受け付けました。',
            'data' => $appointment->load(['patient', 'slot.therapist']),
        ], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $data = $request->validate([
            'appointment_slot_id' => ['sometimes', 'integer', 'exists:appointment_slots,id'],
            'status' => ['sometimes', Rule::in([Appointment::STATUS_BOOKED, Appointment::STATUS_CANCELLED])],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['appointment_slot_id']) && (int) $data['appointment_slot_id'] !== $appointment->appointment_slot_id) {
            $newSlot = AppointmentSlot::query()->with('appointments')->findOrFail($data['appointment_slot_id']);

            if (! $newSlot->is_available) {
                return response()->json(['message' => '移動先の枠は満枠です。'], 422);
            }

            $duplicate = Appointment::query()
                ->where('patient_id', $appointment->patient_id)
                ->where('appointment_slot_id', $newSlot->id)
                ->whereKeyNot($appointment->id)
                ->exists();

            if ($duplicate) {
                return response()->json(['message' => '同じ患者の同一枠予約が既に存在します。'], 422);
            }

            $appointment->appointment_slot_id = $newSlot->id;
        }

        if (isset($data['status'])) {
            $appointment->status = $data['status'];
        }

        if (array_key_exists('staff_notes', $data)) {
            $appointment->staff_notes = $data['staff_notes'];
        }

        $appointment->updated_by_staff_id = $actor->id;
        $appointment->save();

        return response()->json([
            'message' => '予約を更新しました。',
            'data' => $appointment->load(['patient', 'slot.therapist', 'updatedByStaff']),
        ]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'updated_by_staff_id' => $actor->id,
        ]);

        SendPatientMailJob::dispatch(SendPatientMailJob::TYPE_CANCELLATION, $appointment->id);

        $waitlist = Waitlist::query()
            ->where('slot_id', $appointment->appointment_slot_id)
            ->where('status', Waitlist::STATUS_WAITING)
            ->orderBy('priority')
            ->first();

        if ($waitlist) {
            $waitlist->update(['status' => Waitlist::STATUS_PROMOTED]);
            WaitlistPromotedJob::dispatch($waitlist->id);
        }

        return response()->json(['message' => '予約をキャンセルしました。']);
    }

    /**
     * @return array{0: string|null, 1: Model|null}
     */
    private function actorFromRequest(Request $request): array
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return [null, null];
        }

        $token = AccessToken::findValid($plainToken);

        if (! $token) {
            return [null, null];
        }

        $actor = match ($token->actor_type) {
            'patient' => Patient::find($token->actor_id),
            'staff' => Staff::find($token->actor_id),
            default => null,
        };

        return [$token->actor_type, $actor];
    }
}
