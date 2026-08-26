<?php

namespace App\Http\Controllers;

use App\Models\AttendanceScanAudit;
use App\Models\ScannerTerminal;
use App\Models\SecurityFlag;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;

class ScannerSecurityController extends Controller
{
    public function index()
    {
        $terminals = ScannerTerminal::latest()->get();
        $audits = AttendanceScanAudit::latest()->paginate(20, ['*'], 'audits');
        $flags = SecurityFlag::latest('detected_at')->paginate(20, ['*'], 'flags');
        $qrUsers = User::with('role')->whereHas('role', fn ($q) => $q->whereIn('role_name', ['student', 'instructor', 'staff']))->orderBy('name')->get();

        return view('scanner-security.index', compact('terminals', 'audits', 'flags', 'qrUsers'));
    }

    public function storeTerminal(Request $request)
    {
        ScannerTerminal::create($request->validate(['name' => ['required', 'string', 'max:100'], 'location' => ['required', 'string', 'max:255']]));

        return back()->with('success', 'Scanner terminal registered.');
    }

    public function updateTerminal(Request $request, ScannerTerminal $terminal)
    {
        $terminal->update($request->validate(['name' => ['required', 'string', 'max:100'], 'location' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean']]));

        return back()->with('success', 'Scanner terminal updated.');
    }

    public function regenerate(User $user, Request $request, QrCredentialService $credentials)
    {
        abort_unless(in_array($user->role?->role_name, ['student', 'instructor', 'staff'], true), 422);
        $credentials->regenerate($user, $request->user());

        return back()->with('success', "A replacement QR credential was issued for {$user->name}.");
    }

    public function reviewFlag(Request $request, SecurityFlag $flag)
    {
        $data = $request->validate(['status' => ['required', 'in:reviewed,confirmed,dismissed']]);
        $flag->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Security flag updated.');
    }
}
