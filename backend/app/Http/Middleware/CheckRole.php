<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Models\Patient;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            abort(401, 'ログインが必要です。');
        }

        $accessToken = AccessToken::findValid($token);

        if (! $accessToken) {
            abort(401, 'ログインが必要です。');
        }

        $actor = match ($accessToken->actor_type) {
            'patient' => Patient::find($accessToken->actor_id),
            'staff' => Staff::find($accessToken->actor_id),
            default => null,
        };

        if (! $actor) {
            abort(401, 'ログインが必要です。');
        }

        $actualRole = $accessToken->actor_type === 'staff'
            ? $actor->role
            : 'patient';

        if (! in_array($actualRole, $roles, true)) {
            abort(403, '権限がありません。');
        }

        $request->attributes->set('auth_actor_type', $accessToken->actor_type);
        $request->attributes->set('auth_actor_role', $actualRole);
        $request->attributes->set('auth_actor', $actor);

        return $next($request);
    }
}
