<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAccountProtectionService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserAccountStatusController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        abort_if($request->user()->is($user), 422, 'You cannot change your own account status.');

        DB::transaction(function () use ($user, $validated, $request) {
            $lockedUser = app(AdminAccountProtectionService::class)->assertCanChangeStatus($user, $validated['status']);
            $oldStatus = $lockedUser->status;
            $lockedUser->update(['status' => $validated['status']]);

            app(AuditLogger::class)->record('account.status_changed', $lockedUser, [
                'from' => $oldStatus,
                'to' => $validated['status'],
            ], $request->user(), $request);
        });

        return back()->with('success', 'Account '.($validated['status'] === 'active' ? 'activated' : 'deactivated').' successfully.');
    }
}
