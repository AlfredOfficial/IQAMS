<?php

namespace App\Http\Controllers;

use App\Models\OfficeUnit;
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
        OfficeUnit::create($this->validated($request));

        return back()->with('success', 'Office/unit created successfully.');
    }

    public function update(Request $request, OfficeUnit $officeUnit): RedirectResponse
    {
        $officeUnit->update($this->validated($request, $officeUnit));

        return back()->with('success', 'Office/unit updated successfully.');
    }

    public function destroy(OfficeUnit $officeUnit): RedirectResponse
    {
        if ($officeUnit->staff()->exists()) {
            return back()->withErrors(['office_unit' => 'A referenced office/unit cannot be deleted. Deactivate it instead.']);
        }

        $officeUnit->delete();

        return back()->with('success', 'Office/unit deleted successfully.');
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
