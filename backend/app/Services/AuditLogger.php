<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function log(string $action, ?Model $target = null): void
    {
        [$userType, $userId] = $this->actor();

        AuditLog::query()->create([
            'user_id' => $userId,
            'user_type' => $userType ?? 'unknown',
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function actor(): array
    {
        $actor = $this->request->attributes->get('auth_actor');
        $actorType = $this->request->attributes->get('auth_actor_type');

        if ($actor instanceof Staff || $actor instanceof Patient) {
            return [$actorType, $actor->id];
        }

        $plainToken = $this->request->bearerToken();

        if (! $plainToken) {
            return [null, null];
        }

        $token = AccessToken::findValid($plainToken);

        return $token ? [$token->actor_type, $token->actor_id] : [null, null];
    }
}
