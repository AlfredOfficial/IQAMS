<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Services\AuditLogger;
use App\Services\ArchiveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = Course::active()->with('department')->latest()->paginate(10);
        $departments = Department::active()->orderBy('department_name')->get();

        return view('courses.index', compact('courses', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = Department::active()->orderBy('department_name')->get();

        return view('courses.create', compact('departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('archived_at')],
            'course_code' => 'required|string|max:20|unique:courses,course_code,',
            'course_name' => 'required|string|max:255',
        ]);

        $course = Course::create($validated);
        app(AuditLogger::class)->record('record.created', $course, ['record' => 'course'], $request->user(), $request);

        return redirect()->route('courses.index')->with('success', 'Course created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        $departments = Department::active()->orderBy('department_name')->get();

        return view('courses.edit', compact('course', 'departments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('archived_at')],
            'course_code' => 'required|string|max:20|unique:courses,course_code,' . $course->id,
            'course_name' => 'required|string|max:255',
        ]);

        $course->update($validated);
        app(AuditLogger::class)->record('record.updated', $course, ['record' => 'course'], $request->user(), $request);

        return redirect()->route('courses.index')->with('success', 'Course updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Course $course)
    {
        app(ArchiveService::class)->archive($course, $request->user(), $request);

        return redirect()->route('courses.index')->with('success', 'Course archived successfully.');
    }
}
