<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $plainPassword = 'Instructor@' . $validated['employee_no'];

        DB::transaction(function () use ($validated, $plainPassword){
            $instructorRole = Role::where('role_name', 'instructor')->firstOrFail();

            $user = User::create([
                'role_id' => $instructorRole->id,
                'username' => $validated['employee_no'],
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'status' => 'active', 
                'email_verified_at' => now(),
            ]);

            Instructor::create([
                'user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'employee_no' => $validated['employee_no'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'qr_code' =>  $validated['employee_no'],
            ]);

        });

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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
        ]);

        $instructor->update($validated);

        //keep the linked user's display name in sync.
        $instructor->user->update([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
        ]);

        return redirect()->route('instructors.index')->with('success', 'Instructor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Instructor $instructor)
    {
        //deleteng the linked User cascades to delete the Instructor roww too
        //instructors.user_id has cascadesOnDelete in the migration

        $instructor->user()->delete();

        return redirect()->route('instructors.index')->with('success', 'Instructor deleted successfully.');
    }
}
