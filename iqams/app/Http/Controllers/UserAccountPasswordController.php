<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        [$lockedUser, $temporaryPassword] = DB::transaction(function () use ($user, $request): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $role = $lockedUser->primaryRoleName();
            abort_unless(in_array($role, ['student', 'instructor', 'staff'], true), 422, 'This account does not support an administrative password reset.');
            abort_unless($lockedUser->isAccountActive(), 422, 'Inactive accounts cannot receive password reset links.');
            $temporaryPassword = match ($role) {
                'student' => 'Student@'.($lockedUser->student()->value('student_no') ?? $lockedUser->username),
                'instructor' => 'Instructor@'.($lockedUser->instructor()->value('employee_no') ?? $lockedUser->username),
                'staff' => 'Staff@'.($lockedUser->nonTeachingStaff()->value('employee_no') ?? $lockedUser->username),
            };

            $lockedUser->forceFill([
                'password' => Hash::make($temporaryPassword),
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

            return [$lockedUser, $temporaryPassword];
        });

        return back()
            ->with('success', 'Temporary password reset successfully. The previous password and sessions are no longer valid.')
            ->with('generated_password', $temporaryPassword);
    }
}
