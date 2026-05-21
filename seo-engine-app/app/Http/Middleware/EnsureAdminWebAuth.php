<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('admin_authenticated')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
