<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $students = Student::with(['user', 'course', 'section'])->latest()->paginate(10);

        $courses = Course::orderBy('course_name')->get();

        $sections = Section::with('course')->orderBy('section_name')->get();

        return view('students.index', compact('students', 'courses', 'sections'));

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
            'course_id' => 'required|exists:courses,id',
            'section_id' => 'required|exists:sections,id',
            'student_no' => 'required|string|max:50|unique:students,student_no|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255', 
            'email' => 'required|email|unique:users,email',
        ]);

        $plainPassword = 'Student@' . $validated['student_no'];

        DB::transaction(function () use ($validated, $plainPassword) {

            $studentRole = Role::where('role_name', 'student')->firstOrFail();

            $user = User::create([
                'role_id' => $studentRole->id,
                'username' => $validated['student_no'],
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            Student::create([
                'user_id' => $user->id,
                'student_no' => $validated['student_no'],
                'first_name' => $validated['first_name'], 
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'section_id' => $validated['section_id'] ?? null,
                'course_id' => $validated['course_id'],
                'status' => 'active',
                'qr_code' => $validated['student_no'],
            ]);
        });

        return redirect()->route('students.index')->with('success', 'Student created successfully')->with('generated_username', $validated['student_no'])->with('generated_password', $plainPassword);
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'section_id' => 'nullable|exists:sections,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,graduated,dropped',
        ]);
 
        $student->update($validated);
 
        $student->user->update([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
        ]);
 
        return redirect()->route('students.index')->with('success', 'Student updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        $student->user()->delete();

        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }
}
