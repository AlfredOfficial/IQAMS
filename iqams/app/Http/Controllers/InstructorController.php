<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Instructor;
use App\Models\User;
use App\Services\AdminAccountProtectionService;
use App\Services\AuditLogger;
use App\Services\QrCredentialService;
use App\Services\RoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InstructorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instructors = Instructor::with(['user', 'department'])->latest()->paginate(10);

        $departments = Department::active()->orderBy('department_name')->get();

        return view('instructors.index', compact('instructors', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('archived_at')],
            'employee_no' => 'required|string|max:50|unique:instructors,employee_no|unique:users,username',
            'name_prefix' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'professional_credentials' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        $plainPassword = 'Instructor@'.$validated['employee_no'];
        $administrator = $request->user();

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword, $administrator, $avatarPath) {
                $user = User::create([
                    'username' => $validated['employee_no'],
                    'name' => Instructor::formatFullName($validated),
                    'email' => $validated['email'],
                    'avatar_path' => $avatarPath,
                    'password' => Hash::make($plainPassword),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $user->forceFill([
                    'must_change_password' => true,
                    'password_changed_at' => null,
                ])->saveQuietly();
                app(RoleAssignmentService::class)->assign($user, 'instructor', $administrator);

                Instructor::create([
                    'user_id' => $user->id,
                    'department_id' => $validated['department_id'],
                    'employee_no' => $validated['employee_no'],
                    'name_prefix' => $validated['name_prefix'] ?? null,
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'professional_credentials' => $validated['professional_credentials'] ?? null,
                    'qr_code' => null,
                ]);
                app(QrCredentialService::class)->issue($user, $administrator);

                return $user;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($avatarPath);
            throw $exception;
        }

        app(AuditLogger::class)->record('account.created', $user, ['role' => 'instructor'], $administrator, $request);

        return redirect()->route('instructors.index')
            ->with('success', 'Instructor created successfully. Share the temporary credentials below and require a password change on first login.')
            ->with('generated_username', $validated['employee_no'])
            ->with('generated_password', $plainPassword);

    }

    /**
     * Display the specified resource.
     */
    public function show(Instructor $instructor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Instructor $instructor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Instructor $instructor)
    {
        $validated = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('archived_at')],
            'name_prefix' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'professional_credentials' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $oldAvatarPath = $instructor->user->avatar_path;
        $newAvatarPath = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        try {
            DB::transaction(function () use ($validated, $instructor, $newAvatarPath) {
                $instructor->update($validated);
                $instructor->user->update(array_filter([
                    'name' => Instructor::formatFullName($validated),
                    'avatar_path' => $newAvatarPath,
                ], fn ($value) => $value !== null));
            });
        } catch (\Throwable $exception) {
            if ($newAvatarPath) {
                Storage::disk('public')->delete($newAvatarPath);
            }
            throw $exception;
        }

        if ($newAvatarPath && $oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        app(AuditLogger::class)->record('account.profile_updated', $instructor->user, [
            'profile' => 'instructor',
            'profile_id' => $instructor->id,
        ], $request->user(), $request);

        return redirect()->route('instructors.index')->with('success', 'Instructor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Instructor $instructor)
    {
        $user = $instructor->user;

        DB::transaction(function () use ($instructor, $user, $request) {
            $lockedUser = app(AdminAccountProtectionService::class)->assertCanChangeStatus($user, 'inactive');
            $oldStatus = $lockedUser->status;
            $lockedUser->update(['status' => 'inactive']);
            app(AuditLogger::class)->record('account.status_changed', $lockedUser, [
                'from' => $oldStatus,
                'to' => 'inactive',
                'record' => 'instructor_account',
                'profile_id' => $instructor->id,
            ], $request->user(), $request);
        });

        return redirect()->route('instructors.index')->with('success', 'Instructor account deactivated successfully.');
    }
}
