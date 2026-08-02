<?php

namespace App\Http\Controllers;

// use App\Models\Department;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NonTeachingStaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $staffMembers = NonTeachingStaff::with(['user',])->latest()->paginate(10);

        // $departments = Department::orderBy('department_name')->get(); 

        return view('non-teaching-staff.index', compact('staffMembers'));
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
            // 'department_id' => 'required|exists:departments,id',
            'employee_no' => 'required|string|max:50|unique:non_teaching_staff,employee_no|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $plainPassword = 'Staff@' . $validated['employee_no'];

        DB::transaction(function () use ($validated, $plainPassword) {
            $staffRole = Role::where('role_name', 'staff')->firstOrFail();

            $user = User::create([
                'role_id' => $staffRole->id,
                'username' => $validated['employee_no'],
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'status' => 'active', 
                'email_verified_at' => now(),
            ]);

            NonTeachingStaff::create([
                'user_id' => $user->id,
                // 'department_id' => $validated['department_id'],
                'employee_no' => $validated['employee_no'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'qr_code' => $validated['employee_no'],
            ]);
        });

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
            // 'department_id' => 'required|exists:departments,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
        ]);

        $nonTeachingStaff->update($validated);

        $nonTeachingStaff->user->update([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
        ]);
 
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
