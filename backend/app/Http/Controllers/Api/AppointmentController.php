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
use App\Services\AuditLogger;
use App\Services\PatientDataMasker;
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

        $appointments = $query->get();

        if (app(PatientDataMasker::class)->shouldMask($request)) {
            return response()->json([
                'data' => $appointments->map(fn (Appointment $appointment): array => $this->appointmentArray($request, $appointment))->all(),
            ]);
        }

        return response()->json(['data' => $appointments]);
    }

    public function store(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'patient' || ! $actor instanceof Patient) {
            return response()->json(['message' => '患者ログインが必要です。'], 403);
        }

        if (! $actor->canBookRehab()) {
            return response()->json(['message' => '初診診断後にご予約いただけます'], 403);
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

        $duplicate = Appointment::query()
            ->where('patient_id', $actor->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->whereHas('slot', fn ($query) => $query
                ->whereDate('date', $slot->date)
                ->where('starts_at', $slot->starts_at)
            )
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => '同じ日時の予約が既にあります。'], 422);
        }

        $appointment = Appointment::query()->firstOrNew([
            'patient_id' => $actor->id,
            'appointment_slot_id' => $slot->id,
        ]);

        $appointment->status = Appointment::STATUS_BOOKED;
        $appointment->cancelled_at = null;
        $appointment->staff_id ??= Staff::query()->orderBy('id')->value('id');
        $appointment->treatment_type_id ??= TreatmentType::query()->orderBy('id')->value('id');
        $appointment->save();
        app(AuditLogger::class)->log('reservation.created', $appointment);

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
            $appointment->cancelled_at = $data['status'] === Appointment::STATUS_CANCELLED ? now() : null;
        }

        if (array_key_exists('staff_notes', $data)) {
            $appointment->staff_notes = $data['staff_notes'];
        }

        $appointment->updated_by_staff_id = $actor->id;
        $appointment->save();
        app(AuditLogger::class)->log('reservation.updated', $appointment);

        return response()->json([
            'message' => '予約を更新しました。',
            'data' => $this->appointmentArray($request, $appointment->load(['patient', 'slot.therapist', 'updatedByStaff'])),
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
            'cancelled_at' => now(),
            'updated_by_staff_id' => $actor->id,
        ]);
        app(AuditLogger::class)->log('reservation.cancelled', $appointment);

        SendPatientMailJob::dispatch(SendPatientMailJob::TYPE_CANCELLATION, $appointment->id);

        $this->promoteWaitlist($appointment);

        return response()->json(['message' => '予約をキャンセルしました。']);
    }

    public function myReservations(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'patient' || ! $actor instanceof Patient) {
            return response()->json(['message' => '患者ログインが必要です。'], 403);
        }

        $appointments = Appointment::query()
            ->with(['patient', 'slot.therapist', 'treatmentType'])
            ->where('patient_id', $actor->id)
            ->where('status', Appointment::STATUS_BOOKED)
            ->whereHas('slot', fn ($query) => $query->whereDate('date', '>=', now()->toDateString()))
            ->join('appointment_slots', 'appointments.appointment_slot_id', '=', 'appointment_slots.id')
            ->select('appointments.*')
            ->orderBy('appointment_slots.date')
            ->orderBy('appointment_slots.starts_at')
            ->get();

        return response()->json(['data' => $appointments]);
    }

    public function destroyPortal(Request $request, Appointment $appointment): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'patient' || ! $actor instanceof Patient) {
            return response()->json(['message' => '患者ログインが必要です。'], 403);
        }

        if ((int) $appointment->patient_id !== (int) $actor->id) {
            return response()->json(['message' => '自分の予約のみキャンセルできます。'], 403);
        }

        $appointment->load('slot');
        $reservedAt = $this->appointmentDateTime($appointment);

        if (! $reservedAt || now()->addDay()->greaterThanOrEqualTo($reservedAt)) {
            return response()->json(['message' => '予約日時の24時間前を過ぎたためキャンセルできません。'], 422);
        }

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        app(AuditLogger::class)->log('reservation.cancelled', $appointment);

        SendPatientMailJob::dispatch(SendPatientMailJob::TYPE_CANCELLATION, $appointment->id);
        $this->promoteWaitlist($appointment);

        return response()->json(['message' => '予約をキャンセルしました。']);
    }

    public function summary(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff || $actor->role !== 'admin') {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        return response()->json([
            'today_count' => Appointment::query()
                ->where('status', Appointment::STATUS_BOOKED)
                ->whereHas('slot', fn ($query) => $query->whereDate('date', now()->toDateString()))
                ->count(),
            'upcoming_count' => Appointment::query()
                ->where('status', Appointment::STATUS_BOOKED)
                ->whereHas('slot', fn ($query) => $query->whereDate('date', '>=', now()->toDateString()))
                ->count(),
            'cancelled_count' => Appointment::query()
                ->where('status', Appointment::STATUS_CANCELLED)
                ->count(),
        ]);
    }

    public function cancelled(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff || $actor->role !== 'admin') {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $appointments = Appointment::query()
            ->with(['patient', 'slot.therapist'])
            ->where('status', Appointment::STATUS_CANCELLED)
            ->orderByDesc('cancelled_at')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $appointments]);
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

    /**
     * @return array<string, mixed>
     */
    private function appointmentArray(Request $request, Appointment $appointment): array
    {
        $data = $appointment->toArray();

        if ($appointment->relationLoaded('patient') && $appointment->patient && app(PatientDataMasker::class)->shouldMask($request)) {
            $data['patient'] = app(PatientDataMasker::class)->maskPatient($appointment->patient);
        }

        return $data;
    }

    private function appointmentDateTime(Appointment $appointment): ?CarbonImmutable
    {
        if (! $appointment->slot) {
            return null;
        }

        return CarbonImmutable::parse($appointment->slot->date->format('Y-m-d').' '.$appointment->slot->starts_at);
    }

    private function promoteWaitlist(Appointment $appointment): void
    {
        $waitlist = Waitlist::query()
            ->where('slot_id', $appointment->appointment_slot_id)
            ->where('status', Waitlist::STATUS_WAITING)
            ->orderBy('priority')
            ->first();

        if ($waitlist) {
            $waitlist->update(['status' => Waitlist::STATUS_PROMOTED]);
            WaitlistPromotedJob::dispatch($waitlist->id);
        }
    }
}
