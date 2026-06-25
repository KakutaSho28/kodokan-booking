<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Http\Request;

class PatientDataMasker
{
    public function shouldMask(Request $request): bool
    {
        $actorType = $request->attributes->get('auth_actor_type');
        $actorRole = $request->attributes->get('auth_actor_role');

        if ($actorType === 'staff') {
            return $actorRole !== 'admin';
        }

        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return false;
        }

        $token = AccessToken::findValid($plainToken);

        if ($token?->actor_type !== 'staff') {
            return false;
        }

        $staff = Staff::find($token->actor_id);

        return $staff?->role !== 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function maskPatient(Patient $patient): array
    {
        return [
            ...$patient->toArray(),
            'card_number' => $this->maskChartNumber($patient->card_number),
            'chart_number' => $this->maskChartNumber($patient->card_number),
            'birth_date' => $patient->birth_date ? '****年生まれ' : null,
        ];
    }

    public function maskChartNumber(?string $chartNumber): ?string
    {
        if (! $chartNumber) {
            return null;
        }

        return '****'.substr($chartNumber, -4);
    }
}
