<?php

namespace App\Http\Controllers;

use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class NonTeachingStaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffMembers = NonTeachingStaff::with(['user', 'officeUnit'])->latest()->paginate(10);

        $officeUnits = OfficeUnit::where('is_active', true)->orderBy('name')->get();

        return view('non-teaching-staff.index', compact('staffMembers', 'officeUnits'));
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
            'office_unit_id' => ['required', Rule::exists('office_units', 'id')->where('is_active', true)],
            'employee_no' => 'required|string|max:50|unique:non_teaching_staff,employee_no|unique:users,username',
            'name_prefix' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'name_suffix' => 'nullable|string|max:100',
            'email' => 'required|email|unique:users,email',
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated = $this->normalizeOptionalNameParts($validated);

        $avatarPath = $request->file('avatar')->store('avatars', 'public');

        $plainPassword = 'Staff@'.$validated['employee_no'];

        try {
            DB::transaction(function () use ($validated, $plainPassword, $avatarPath) {
                $staffRole = Role::where('role_name', 'staff')->firstOrFail();

                $user = User::create([
                    'role_id' => $staffRole->id,
                    'username' => $validated['employee_no'],
                    'name' => NonTeachingStaff::formatFullName($validated),
                    'email' => $validated['email'],
                    'avatar_path' => $avatarPath,
                    'password' => Hash::make($plainPassword),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                NonTeachingStaff::create([
                    'user_id' => $user->id,
                    'office_unit_id' => $validated['office_unit_id'],
                    'employee_no' => $validated['employee_no'],
                    'name_prefix' => $validated['name_prefix'],
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'],
                    'last_name' => $validated['last_name'],
                    'name_suffix' => $validated['name_suffix'],
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
            'office_unit_id' => ['required', Rule::exists('office_units', 'id')->where('is_active', true)],
            'name_prefix' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'name_suffix' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $validated = $this->normalizeOptionalNameParts($validated);

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

    private function normalizeOptionalNameParts(array $validated): array
    {
        foreach (['name_prefix', 'middle_name', 'name_suffix'] as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $validated[$field] = $value === '' ? null : $value;
        }

        return $validated;
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
