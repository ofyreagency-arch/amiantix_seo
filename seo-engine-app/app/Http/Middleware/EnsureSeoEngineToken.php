<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeoEngineToken
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $raw = (string) $request->bearerToken();

        if ($raw === '') {
            abort(401, 'Missing API token.');
        }

        $site = SeoSite::resolveByToken($raw);

        if (! $site) {
            abort(401, 'Invalid or inactive site token.');
        }

        $this->context->loadFromSite($site);

        return $next($request);
    }
}
