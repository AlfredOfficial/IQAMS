<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class InstructorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instructors = Instructor::with(['user', 'department'])->latest()->paginate(10);

        $departments = Department::orderBy('department_name')->get();

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
            'department_id' => 'required|exists:departments,id',
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

        try {
            DB::transaction(function () use ($validated, $plainPassword, $avatarPath) {
                $instructorRole = Role::where('role_name', 'instructor')->firstOrFail();

                $user = User::create([
                    'role_id' => $instructorRole->id,
                    'username' => $validated['employee_no'],
                    'name' => Instructor::formatFullName($validated),
                    'email' => $validated['email'],
                    'avatar_path' => $avatarPath,
                    'password' => Hash::make($plainPassword),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                Instructor::create([
                    'user_id' => $user->id,
                    'department_id' => $validated['department_id'],
                    'employee_no' => $validated['employee_no'],
                    'name_prefix' => $validated['name_prefix'] ?? null,
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'professional_credentials' => $validated['professional_credentials'] ?? null,
                    'qr_code' => $validated['employee_no'],
                ]);
                app(QrCredentialService::class)->issue($user);

            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($avatarPath);
            throw $exception;
        }

        return redirect()->route('instructors.index')->with('success', 'Instructor created successfully.')->with('generated_username', $validated['employee_no'])->with('generated_password', $plainPassword);

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
            'department_id' => 'required|exists:departments,id',
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

        return redirect()->route('instructors.index')->with('success', 'Instructor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Instructor $instructor)
    {
        // deleteng the linked User cascades to delete the Instructor roww too
        // instructors.user_id has cascadesOnDelete in the migration

        $instructor->user()->delete();

        return redirect()->route('instructors.index')->with('success', 'Instructor deleted successfully.');
    }
}
