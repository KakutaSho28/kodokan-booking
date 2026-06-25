<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Patient;
use App\Models\Shift;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'is_day_off' => ['required', 'boolean'],
        ]);

        if (! $data['is_day_off']) {
            validator($data, [
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            ])->validate();
        }

        $shift = Shift::query()->updateOrCreate(
            [
                'staff_id' => $data['staff_id'],
                'work_date' => $data['work_date'],
            ],
            [
                'start_time' => $data['is_day_off'] ? null : $data['start_time'].':00',
                'end_time' => $data['is_day_off'] ? null : $data['end_time'].':00',
                'is_day_off' => $data['is_day_off'],
            ],
        );

        return response()->json([
            'message' => 'シフトを保存しました。',
            'data' => $shift,
        ]);
    }

    private function isAdminRequest(Request $request): bool
    {
        [$role, $actor] = $this->actorFromRequest($request);

        return $role === 'staff' && $actor instanceof Staff && $actor->isAdmin();
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
