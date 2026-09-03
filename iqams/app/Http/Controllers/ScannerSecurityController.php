<?php

namespace App\Http\Controllers;

use App\Jobs\IssueMissingQrCredentials;
use App\Models\AttendanceScanAudit;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\OfficeUnit;
use App\Models\Section;
use App\Models\ScannerTerminal;
use App\Models\SecurityFlag;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;

class ScannerSecurityController extends Controller
{
    public function index()
    {
        $terminals = ScannerTerminal::latest()->get();
        $audits = AttendanceScanAudit::latest()->paginate(20, ['*'], 'audits');
        $flags = SecurityFlag::latest('detected_at')->paginate(20, ['*'], 'flags');
        $qrBatches = AuditLog::query()
            ->with('actor')
            ->whereIn('action', ['qr.batch_queued', 'qr.batch_completed'])
            ->latest('created_at')
            ->limit(20)
            ->get();
        $qrUsers = User::with('roles')
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['student', 'instructor', 'staff'])->where('guard_name', 'web'))
            ->orderBy('name')
            ->get();

        $departments = Department::active()->orderBy('department_name')->get(['id', 'department_code', 'department_name']);
        $officeUnits = OfficeUnit::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $courses = Course::active()->orderBy('course_code')->get(['id', 'course_code', 'course_name']);
        $sections = Section::active()->orderBy('section_name')->get(['id', 'section_name']);

        return view('scanner-security.index', compact('terminals', 'audits', 'flags', 'qrUsers', 'qrBatches', 'departments', 'officeUnits', 'courses', 'sections'));
    }

    public function queueQrBatch(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(['student', 'instructor', 'staff'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'office_unit_id' => ['nullable', 'integer', 'exists:office_units,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $batch = Bus::batch([new IssueMissingQrCredentials($filters, $request->user()->id)])
            ->name('IQAMS missing QR credential issuance')
            ->allowFailures()
            ->dispatch();

        app(AuditLogger::class)->record('qr.batch_queued', null, [
            'batch_id' => $batch->id,
            'filters' => $filters,
        ], $request->user(), $request);

        return back()->with('success', 'QR issuance batch queued. Existing active credentials will be skipped.');
    }

    public function storeTerminal(Request $request)
    {
        $terminal = ScannerTerminal::create($request->validate(['name' => ['required', 'string', 'max:100'], 'location' => ['required', 'string', 'max:255']]));
        app(AuditLogger::class)->record('security.terminal_created', $terminal, [], $request->user(), $request);

        return back()->with('success', 'Scanner terminal registered.');
    }

    public function updateTerminal(Request $request, ScannerTerminal $terminal)
    {
        $terminal->update($request->validate(['name' => ['required', 'string', 'max:100'], 'location' => ['required', 'string', 'max:255'], 'is_active' => ['required', 'boolean']]));
        app(AuditLogger::class)->record('security.terminal_updated', $terminal, [], $request->user(), $request);

        return back()->with('success', 'Scanner terminal updated.');
    }

    public function regenerate(User $user, Request $request, QrCredentialService $credentials)
    {
        abort_unless($user->hasAnyRole(['student', 'instructor', 'staff']), 422);
        $credentials->regenerate($user, $request->user());

        return back()->with('success', "A replacement QR credential was issued for {$user->name}.");
    }

    public function reviewFlag(Request $request, SecurityFlag $flag)
    {
        $data = $request->validate(['status' => ['required', 'in:reviewed,confirmed,dismissed']]);
        $flag->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        app(AuditLogger::class)->record('security_flag.reviewed', $flag, [
            'status' => $data['status'],
        ], $request->user(), $request);

        return back()->with('success', 'Security flag updated.');
    }
}
