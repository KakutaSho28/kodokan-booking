<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Models\AccessToken;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'chart_number' => ['nullable', 'string', 'max:255'],
            'is_diagnosed' => ['nullable', Rule::in(['0', '1', 0, 1, true, false, 'true', 'false'])],
        ]);

        $patients = Patient::query()
            ->with('assignedTherapist')
            ->when(isset($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(isset($filters['chart_number']), fn ($query) => $query->where('card_number', 'like', '%'.$filters['chart_number'].'%'))
            ->when(array_key_exists('is_diagnosed', $filters), function ($query) use ($filters): void {
                $query->where('is_diagnosed', filter_var($filters['is_diagnosed'], FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->get()
            ->values();

        return PatientResource::collection($patients)->response();
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $data = $request->validate($this->patientRules());
        $data['card_number'] = $data['chart_number'] ?? $data['card_number'];
        unset($data['chart_number']);
        $data['is_diagnosed'] = (bool) ($data['is_diagnosed'] ?? false);
        $data['has_rehab_clearance'] = $data['is_diagnosed'];
        $data['is_first_visit'] = ! $data['is_diagnosed'];

        $patient = Patient::query()->create($data);

        return response()->json([
            'message' => '患者を登録しました。',
            'data' => new PatientResource($patient->load('assignedTherapist')),
        ], 201);
    }

    public function show(Request $request, Patient $patient): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $patient->load([
            'assignedTherapist',
            'appointments' => fn ($query) => $query
                ->with(['slot.therapist', 'staff', 'treatmentType'])
                ->join('appointment_slots', 'appointments.appointment_slot_id', '=', 'appointment_slots.id')
                ->select('appointments.*')
                ->orderByDesc('appointment_slots.date')
                ->orderByDesc('appointment_slots.starts_at')
                ->limit(20),
        ]);

        return response()->json(['data' => new PatientResource($patient)]);
    }

    public function update(Request $request, Patient $patient): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $data = $request->validate($this->patientRules($patient));
        $data['card_number'] = $data['chart_number'] ?? $data['card_number'];
        unset($data['chart_number'], $data['is_diagnosed']);

        $patient->update($data);

        return response()->json([
            'message' => '患者情報を更新しました。',
            'data' => new PatientResource($patient->load('assignedTherapist')),
        ]);
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $patient->delete();

        return response()->json(['message' => '患者を削除しました。']);
    }

    public function diagnose(Request $request, Patient $patient): JsonResponse
    {
        if (! $this->isAdminRequest($request)) {
            return response()->json(['message' => '管理者権限が必要です。'], 403);
        }

        $patient->update([
            'is_diagnosed' => true,
            'has_rehab_clearance' => true,
            'is_first_visit' => false,
        ]);

        return response()->json([
            'message' => '診断済みに更新しました。',
            'data' => new PatientResource($patient->load('assignedTherapist')),
        ]);
    }

    public function reservations(Request $request, Patient $patient): JsonResponse
    {
        [$role, $actor] = $this->actorFromRequest($request);

        if ($role !== 'staff' || ! $actor instanceof Staff) {
            return response()->json(['message' => 'スタッフログインが必要です。'], 403);
        }

        $reservations = Appointment::query()
            ->with(['slot.therapist', 'staff', 'treatmentType'])
            ->where('patient_id', $patient->id)
            ->join('appointment_slots', 'appointments.appointment_slot_id', '=', 'appointment_slots.id')
            ->select('appointments.*')
            ->orderByDesc('appointment_slots.date')
            ->orderByDesc('appointment_slots.starts_at')
            ->get();

        return response()->json(['data' => $reservations]);
    }

    /**
     * @return array<string, mixed>
     */
    private function patientRules(?Patient $patient = null): array
    {
        return [
            'card_number' => ['required_without:chart_number', 'string', 'max:255', Rule::unique('patients', 'card_number')->ignore($patient)],
            'chart_number' => ['required_without:card_number', 'string', 'max:255', Rule::unique('patients', 'card_number')->ignore($patient)],
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'email' => ['nullable', 'email', 'max:255'],
            'assigned_therapist_id' => ['nullable', 'integer', 'exists:therapists,id'],
            'is_diagnosed' => ['sometimes', 'boolean'],
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
