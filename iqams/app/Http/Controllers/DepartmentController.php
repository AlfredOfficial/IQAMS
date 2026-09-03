<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\ArchiveService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::active()->latest()->paginate(10);
        return view('departments.index', compact('departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('departments.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_code' => 'required|string|max:20|unique:departments,department_code',
            'department_name' => 'required|string|max:255',
        ]);

        $department = Department::create($validated);
        app(AuditLogger::class)->record('record.created', $department, ['record' => 'department'], $request->user(), $request);

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'department_code' => 'required|string|max:20|unique:departments,department_code,' . $department->id,
            'department_name' => 'required|string|max:255', 
        ]);

        $department->update($validated);
        app(AuditLogger::class)->record('record.updated', $department, ['record' => 'department'], $request->user(), $request);

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Department $department)
    {
        app(ArchiveService::class)->archive($department, $request->user(), $request);

        return redirect()->route('departments.index')->with('success', 'Department archived successfully.');
    }
}
