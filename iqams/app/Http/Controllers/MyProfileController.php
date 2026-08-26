<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $user->load(match ($user->role?->role_name) {
            'student' => ['role', 'student.course', 'student.section'],
            'instructor' => ['role', 'instructor.department'],
            'staff' => ['role', 'nonTeachingStaff'],
            default => ['role'],
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
            $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
        }

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

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();
        } catch (Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath !== null && $oldAvatarPath !== null && $oldAvatarPath !== $newAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $route = $user->isStaff() ? 'staff.profile.edit' : 'my-profile.edit';

        return redirect()->route($route)->with('status', 'profile-updated');
    }
}
