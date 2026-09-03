<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Section;
use App\Services\AuditLogger;
use App\Services\ArchiveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sections = Section::active()->with(['course', 'schedules.subject', 'schedules.instructor'])->latest()->paginate(10);
        
        $courses = Course::active()->orderBy('course_name')->get();

        return view('sections.index', compact('sections', 'courses'));
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
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('archived_at')],
            'section_name' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'semester' => 'required|in:1st,2nd,summer',
        ]);

        $section = Section::create($validated);
        app(AuditLogger::class)->record('record.created', $section, ['record' => 'section'], $request->user(), $request);

        return redirect()->route('sections.index')->with('success', 'Section created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        $section->load(['course', 'schedules.subject', 'schedules.instructor']);
        return view('sections.show', compact('section'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Section $section)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        $validated = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('archived_at')],
            'section_name' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'semester' => 'required|in:1st,2nd,summer',
        ]);

        $section->update($validated);
        app(AuditLogger::class)->record('record.updated', $section, ['record' => 'section'], $request->user(), $request);

        return redirect()->route('sections.index')->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Section $section)
    {
        app(ArchiveService::class)->archive($section, $request->user(), $request);

        return redirect()->route('sections.index')->with('success', 'Section archived successfully');
    }
}
