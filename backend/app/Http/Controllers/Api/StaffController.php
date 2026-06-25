<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Patient;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function publicIndex(): JsonResponse
    {
        return response()->json([
            'data' => Staff::query()
                ->select(['id', 'staff_id', 'name', 'role', 'is_active'])
                ->where('is_active', true)
                ->whereIn('role', ['staff', 'admin'])
                ->orderBy('staff_id')
                ->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        return response()->json([
            'data' => Staff::query()
                ->whereIn('role', ['staff', 'admin'])
                ->orderBy('staff_id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $data = $request->validate($this->rules());
        $data['is_active'] = $data['is_active'] ?? true;

        $staff = Staff::query()->create($data);

        return response()->json([
            'message' => 'スタッフを登録しました。',
            'data' => $staff,
        ], 201);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $data = $request->validate($this->rules($staff, false));

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $staff->update($data);

        return response()->json([
            'message' => 'スタッフ情報を更新しました。',
            'data' => $staff->refresh(),
        ]);
    }

    public function deactivate(Request $request, Staff $staff): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $staff->update(['is_active' => false]);

        return response()->json([
            'message' => 'スタッフを無効化しました。',
            'data' => $staff->refresh(),
        ]);
    }

    public function shifts(Request $request, Staff $staff): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01');

        return response()->json([
            'data' => $staff->shifts()
                ->whereBetween('work_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->orderBy('work_date')
                ->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Staff $staff = null, bool $requirePassword = true): array
    {
        return [
            'staff_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'staff_id')->ignore($staff)],
            'name' => ['required', 'string', 'max:255'],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
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
