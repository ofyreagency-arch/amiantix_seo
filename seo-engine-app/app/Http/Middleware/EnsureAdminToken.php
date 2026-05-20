<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.seo_engine.admin_token', '');
        $provided   = (string) $request->bearerToken();

        if ($configured === '' || ! hash_equals($configured, $provided)) {
            abort(401, 'Invalid admin token.');
        }

        return $next($request);
    }
}
