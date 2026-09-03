<?php

namespace App\Http\Controllers;

use App\Models\OfficeUnit;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeUnitController extends Controller
{
    public function index(): View
    {
        $officeUnits = OfficeUnit::withCount('staff')->orderBy('name')->paginate(15);

        return view('office-units.index', compact('officeUnits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $officeUnit = OfficeUnit::create($this->validated($request));
        app(AuditLogger::class)->record('record.created', $officeUnit, ['record' => 'office_unit'], $request->user(), $request);

        return back()->with('success', 'Office/unit created successfully.');
    }

    public function update(Request $request, OfficeUnit $officeUnit): RedirectResponse
    {
        $officeUnit->update($this->validated($request, $officeUnit));
        app(AuditLogger::class)->record('record.updated', $officeUnit, ['record' => 'office_unit'], $request->user(), $request);

        return back()->with('success', 'Office/unit updated successfully.');
    }

    public function destroy(Request $request, OfficeUnit $officeUnit): RedirectResponse
    {
        $officeUnit->update(['is_active' => false]);
        app(AuditLogger::class)->record('record.archived', $officeUnit, ['record' => 'office_unit'], $request->user(), $request);

        return back()->with('success', 'Office/unit deactivated successfully.');
    }

    private function validated(Request $request, ?OfficeUnit $officeUnit = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('office_units')->ignore($officeUnit)],
            'name' => ['required', 'string', 'max:255', Rule::unique('office_units')->ignore($officeUnit)],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
