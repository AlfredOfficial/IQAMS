<?php

namespace App\Http\Controllers\Auth;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordController
{
    public function create(): View
    {
        return view('auth.force-password-change');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validateWithBag('forcePasswordChange', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $audit->record('account.password_changed', $user, ['source' => 'forced_change'], $user, $request);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'password-updated');
    }
}
