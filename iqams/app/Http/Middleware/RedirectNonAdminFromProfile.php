<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectNonAdminFromProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasRole('admin')) {
            return redirect()->route($request->user()->isStudent() ? 'student.profile' : 'my-profile.edit');
        }

        return $next($request);
    }
}
