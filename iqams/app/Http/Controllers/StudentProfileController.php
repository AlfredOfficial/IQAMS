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

class StudentProfileController extends Controller
{
    public function show(Request $request): View
    {
        $student = $this->student($request);
        $student->load(['course.department', 'section']);

        return view('student.profile', compact('student'));
    }

    public function settings(Request $request): View
    {
        $student = $this->student($request);

        return view('student.settings', compact('student'));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]*$/'],
        ]);

        $user->email = $validated['email'];
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        $student->update(['contact_number' => $validated['contact_number'] ?? null]);

        return back()->with('success', 'Contact information updated successfully.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->student($request);
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $newPath = $validated['avatar']->store('avatars', 'public');
        $oldPath = $user->avatar_path;

        try {
            $user->update(['avatar_path' => $newPath]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Profile photo updated successfully.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->student($request);
        $oldPath = $user->avatar_path;
        $user->update(['avatar_path' => null]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Profile photo removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->student($request);
        $validated = $request->validateWithBag('passwordUpdate', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return back()->with('success', 'Password changed successfully.');
    }

    private function student(Request $request)
    {
        return $request->user()->student
            ?? abort(403, 'No student profile linked to this account.');
    }
}
