<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\UserAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            abort(401, 'Missing user token.');
        }

        $rawToken = trim(substr($header, 7));

        if ($rawToken === '') {
            abort(401, 'Missing user token.');
        }

        $accessToken = UserAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $accessToken || ! $accessToken->user) {
            abort(401, 'Invalid user token.');
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            abort(401, 'Expired user token.');
        }

        $accessToken->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('frontend_access_token', $accessToken);

        return $next($request);
    }
}
