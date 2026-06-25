<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\AppointmentSlot;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\Waitlist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if (! $actor) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $data = $request->validate([
            'slot_id' => ['nullable', 'integer', 'exists:appointment_slots,id'],
        ]);

        $query = Waitlist::query()
            ->with(['patient', 'slot.therapist'])
            ->where('status', Waitlist::STATUS_WAITING)
            ->orderBy('priority');

        if (isset($data['slot_id'])) {
            if ($role !== 'staff' || ! $actor instanceof Staff) {
                return response()->json(['message' => 'スタッフログインが必要です。'], 403);
            }

            $query->where('slot_id', $data['slot_id']);
        } else {
            if ($role !== 'patient' || ! $actor instanceof Patient) {
                return response()->json(['message' => '患者ログインが必要です。'], 403);
            }

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

        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:appointment_slots,id'],
        ]);

        $slot = AppointmentSlot::query()->with('appointments')->findOrFail($data['slot_id']);

        if ($slot->is_available) {
            return response()->json(['message' => '空きがある枠はキャンセル待ち登録できません。'], 422);
        }

        $exists = Waitlist::query()
            ->where('patient_id', $actor->id)
            ->where('slot_id', $slot->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slot_id' => ['この枠は既にキャンセル待ち登録済みです。'],
            ]);
        }

        $nextPriority = ((int) Waitlist::query()
            ->where('slot_id', $slot->id)
            ->max('priority')) + 1;

        $waitlist = Waitlist::query()->create([
            'patient_id' => $actor->id,
            'slot_id' => $slot->id,
            'priority' => $nextPriority,
            'status' => Waitlist::STATUS_WAITING,
        ]);

        return response()->json([
            'message' => 'キャンセル待ちに登録しました。',
            'data' => $waitlist->load(['patient', 'slot.therapist']),
        ], 201);
    }

    public function destroy(Request $request, Waitlist $waitlist): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'patient' || ! $actor instanceof Patient) {
            return response()->json(['message' => '患者ログインが必要です。'], 403);
        }

        if ((int) $waitlist->patient_id !== (int) $actor->id) {
            return response()->json(['message' => '自分のキャンセル待ちのみ取り消せます。'], 403);
        }

        $waitlist->delete();

        return response()->json(['message' => 'キャンセル待ちを取り消しました。']);
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
