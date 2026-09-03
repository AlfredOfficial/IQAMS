<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\AuditLogger;
use App\Services\ArchiveService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subjects = Subject::active()->latest()->paginate(10);

        return view('subjects.index', compact('subjects'));
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
            'subject_code' => 'required|string|max:20|unique:subjects,subject_code',
            'subject_name' => 'required|string|max:255',
            'units' => 'required|numeric|min:0|max:10',
        ]);

        $subject = Subject::create($validated);
        app(AuditLogger::class)->record('record.created', $subject, ['record' => 'subject'], $request->user(), $request);

        return redirect()->route('subjects.index')->with('success', 'Subject creted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'subject_code' => 'required|string|max:20|unique:subjects,subject_code,' . $subject->id,
            'subject_name' => 'required|string|max:255',
            'units' => 'required|numeric|min:0|max:10',
        ]);

        $subject->update($validated);
        app(AuditLogger::class)->record('record.updated', $subject, ['record' => 'subject'], $request->user(), $request);

        return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Subject $subject)
    {
        app(ArchiveService::class)->archive($subject, $request->user(), $request);

        return redirect()->route('subjects.index')->with('success', 'Subject archived successfully.');
    }
}
