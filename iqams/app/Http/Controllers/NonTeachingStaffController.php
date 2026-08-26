<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class NonTeachingStaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffMembers = NonTeachingStaff::with(['user', 'department'])->latest()->paginate(10);

        $departments = Department::orderBy('department_name')->get();

        return view('non-teaching-staff.index', compact('staffMembers', 'departments'));
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
            'department_id' => 'required|exists:departments,id',
            'employee_no' => 'required|string|max:50|unique:non_teaching_staff,employee_no|unique:users,username',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        $plainPassword = 'Staff@'.$validated['employee_no'];

        try {
            DB::transaction(function () use ($validated, $plainPassword, $avatarPath) {
                $staffRole = Role::where('role_name', 'staff')->firstOrFail();

                $user = User::create([
                    'role_id' => $staffRole->id,
                    'username' => $validated['employee_no'],
                    'name' => implode(' ', array_filter([
                        $validated['first_name'],
                        $validated['middle_name'] ?? null,
                        $validated['last_name'],
                    ], fn ($part) => filled($part))),
                    'email' => $validated['email'],
                    'avatar_path' => $avatarPath,
                    'password' => Hash::make($plainPassword),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                NonTeachingStaff::create([
                    'user_id' => $user->id,
                    'department_id' => $validated['department_id'],
                    'employee_no' => $validated['employee_no'],
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'qr_code' => $validated['employee_no'],
                ]);
                app(QrCredentialService::class)->issue($user);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($avatarPath);
            throw $exception;
        }

        return redirect()->route('non-teaching-staff.index')->with('success', 'Staff member created successfully.')->with('generated_username', $validated['employee_no'])->with('generated_password', $plainPassword);

    }

    /**
     * Display the specified resource.
     */
    public function show(NonTeachingStaff $nonTeachingStaff)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(NonTeachingStaff $nonTeachingStaff)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NonTeachingStaff $nonTeachingStaff)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $oldAvatarPath = $nonTeachingStaff->user->avatar_path;
        $newAvatarPath = $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null;

        try {
            DB::transaction(function () use ($validated, $nonTeachingStaff, $newAvatarPath) {
                $nonTeachingStaff->update($validated);
                $nonTeachingStaff->user->update(array_filter([
                    'name' => $nonTeachingStaff->fullName(),
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

        return redirect()->route('non-teaching-staff.index')->with('success', 'Staff member updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(NonTeachingStaff $nonTeachingStaff)
    {
        DB::transaction(function () use ($nonTeachingStaff) {
            $nonTeachingStaff->delete();
            $nonTeachingStaff->user()->delete();
        });

        return redirect()->route('non-teaching-staff.index')->with('success', 'Staff member deleted successfully.');
    }
}
