<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountInvitationService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserAccountPasswordController extends Controller
{
    public function reset(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot reset your own password here.');

        DB::transaction(function () use ($user, $request): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $role = $lockedUser->primaryRoleName();
            abort_unless(in_array($role, ['student', 'instructor', 'staff'], true), 422, 'This account does not support an administrative password reset.');
            abort_unless($lockedUser->isAccountActive(), 422, 'Inactive accounts cannot receive password reset links.');
            $lockedUser->forceFill([
                'password' => Hash::make(Str::random(64)),
                'must_change_password' => true,
                'password_changed_at' => null,
                'remember_token' => Str::random(60),
                'session_version' => (int) $lockedUser->session_version + 1,
            ])->save();
            Password::broker()->deleteToken($lockedUser);

            app(AuditLogger::class)->record('account.password_reset_required', $lockedUser, [
                'role' => $role,
                'source' => 'admin',
            ], $request->user(), $request);
            app(AccountInvitationService::class)->queue($lockedUser, $request->user());
        });

        return back()
            ->with('success', 'A password reset email has been queued. The previous password and sessions are no longer valid.');
    }
}
