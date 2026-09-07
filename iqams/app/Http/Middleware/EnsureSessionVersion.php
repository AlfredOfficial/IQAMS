<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // Read current state even when the guard already holds a user instance.
            $version = User::whereKey($user->id)->value('session_version');

            if ($version === null || (int) $request->session()->get('auth.session_version', 0) !== (int) $version) {
                Auth::guard('web')->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Continue as a guest: auth middleware protects private routes,
                // while an emailed setup URL can still be opened immediately.
            }
        }

        return $next($request);
    }
}
