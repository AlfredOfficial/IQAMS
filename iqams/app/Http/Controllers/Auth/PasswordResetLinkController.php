<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // The broker securely creates, stores, throttles, and sends the token
        // through the user's standard password-reset notification.
        Password::sendResetLink($request->only('email'));

        // Always return the same response for valid input. The password broker
        // still handles user lookup, notification delivery, and per-address
        // throttling without exposing whether the account exists.
        return back()->with(
            'status',
            __('If an account exists with this email address, we have sent a password reset link.')
        );
    }
}
