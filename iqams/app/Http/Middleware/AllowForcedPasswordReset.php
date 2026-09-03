<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowForcedPasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->must_change_password) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
