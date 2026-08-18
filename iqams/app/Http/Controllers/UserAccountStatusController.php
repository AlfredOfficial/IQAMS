<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAccountStatusController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        abort_if($request->user()->is($user), 422, 'You cannot change your own account status.');

        $user->update(['status' => $validated['status']]);

        return back()->with('success', 'Account '.($validated['status'] === 'active' ? 'activated' : 'deactivated').' successfully.');
    }
}
