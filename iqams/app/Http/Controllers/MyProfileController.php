<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProfileImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class MyProfileController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            return redirect()->route('student.profile');
        }

        $user->load(match ($user->primaryRoleName()) {
            'student' => ['roles', 'student.course', 'student.section'],
            'instructor' => ['roles', 'instructor.department'],
            'staff' => ['roles', 'nonTeachingStaff'],
            default => ['roles'],
        });

        return $user->isInstructor()
            ? view('instructor.profile', compact('user'))
            : view('my-profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        // The authenticated user is the only update target; no request-supplied ID is used.
        $user = $request->user();

        if ($user->isStudent()) {
            abort(403, 'Students may only update permitted fields through Student Profile.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            $newAvatarPath = app(ProfileImageService::class)->store($request->file('avatar'));
        }

        $passwordChanged = ! empty($validated['password']);

        try {
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($newAvatarPath !== null) {
                $user->avatar_path = $newAvatarPath;
            }

            if ($passwordChanged) {
                $user->forceFill([
                    'password' => $validated['password'],
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                ]);
            }

            $user->save();
        } catch (Throwable $exception) {
            if ($newAvatarPath !== null) {
                app(ProfileImageService::class)->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath !== null && $oldAvatarPath !== null && $oldAvatarPath !== $newAvatarPath) {
            app(ProfileImageService::class)->delete($oldAvatarPath);
        }

        if ($passwordChanged) {
            app(AuditLogger::class)->record(
                'account.password_changed',
                $user,
                ['source' => 'profile'],
                $user,
                $request,
            );
        }

        if (! $passwordChanged) {
            app(AuditLogger::class)->record('account.profile_updated', $user, [], $user, $request);
        }

        $route = $user->isStaff() ? 'staff.profile.edit' : 'my-profile.edit';

        return redirect()->route($route)->with('status', 'profile-updated');
    }
}
