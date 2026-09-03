<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserAccountPasswordController extends Controller
{
    public function reset(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot reset your own password here.');

        $role = $user->primaryRoleName();
        abort_unless(in_array($role, ['student', 'instructor', 'staff'], true), 422, 'This account does not support an administrative temporary password reset.');

        $identifier = match ($role) {
            'student' => $user->student?->student_no ?? $user->username,
            'instructor' => $user->instructor?->employee_no ?? $user->username,
            'staff' => $user->nonTeachingStaff?->employee_no ?? $user->username,
        };
        $plainPassword = ucfirst($role).'@'.$identifier;

        DB::transaction(function () use ($user, $plainPassword, $role, $request): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->forceFill([
                'password' => Hash::make($plainPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            app(AuditLogger::class)->record('account.password_reset_required', $lockedUser, [
                'role' => $role,
                'source' => 'admin',
            ], $request->user(), $request);
        });

        return back()
            ->with('success', 'Temporary password reset successfully. Share the credentials below and require a password change on first login.')
            ->with('generated_username', $user->username)
            ->with('generated_password', $plainPassword);
    }
}
