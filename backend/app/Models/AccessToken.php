<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    protected $fillable = [
        'token_hash',
        'actor_type',
        'actor_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function issue(string $actorType, int $actorId): string
    {
        $plainToken = bin2hex(random_bytes(32));

        self::create([
            'token_hash' => hash('sha256', $plainToken),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'expires_at' => now()->addHours(12),
        ]);

        return $plainToken;
    }

    public static function findValid(string $plainToken): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
