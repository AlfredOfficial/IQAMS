<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceAlreadyRecordedException;
use App\Models\AttendanceLog;
use App\Models\ScannerTerminal;
use App\Services\AccountStatusService;
use App\Services\QrAttendanceService;
use App\Services\QrIdentityResolver;
use App\Services\ScanSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceScannerController extends Controller
{
    public function index(Request $request): View
    {
        $terminals = ScannerTerminal::where('is_active', true)->orderBy('name')->get(['id', 'name', 'location']);
        $terminal = $terminals->firstWhere('id', (int) $request->session()->get('scanner_terminal_id'));

        return view('attendance-scanner.index', compact('terminals', 'terminal'));
    }

    public function selectTerminal(Request $request)
    {
        $validated = $request->validate(['scanner_terminal_id' => ['required', 'exists:scanner_terminals,id']]);
        $terminal = ScannerTerminal::whereKey($validated['scanner_terminal_id'])->where('is_active', true)->firstOrFail();
        $request->session()->put('scanner_terminal_id', $terminal->id);

        return redirect()->route('attendance-scanner.index');
    }

    public function scan(
        Request $request,
        QrIdentityResolver $resolver,
        QrAttendanceService $attendance,
        ScanSecurityService $security,
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'qr_code' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            ]);
            $qrCode = trim($validated['qr_code']);
            $resolved = $resolver->resolveWithMetadata($qrCode);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'QR code rejected.';
            $security->audit($request, str_contains(strtolower($message), 'revoked') ? 'revoked' : 'invalid', [
                'failure_category' => 'credential_rejected',
            ]);

            return $this->result('rejected', 'Invalid QR Code', $message, status: 422);
        }

        $terminal = $request->attributes->get('scanner_terminal');
        $user = $resolved['user']->loadMissing([
            'roles', 'student.course.department', 'instructor.department', 'nonTeachingStaff.officeUnit',
        ]);
        $credentialType = $resolved['is_legacy'] ? 'legacy' : 'random';

        try {
            $log = $attendance->record($qrCode, $terminal->location);
        } catch (AttendanceAlreadyRecordedException $exception) {
            $security->audit($request, 'duplicate', [
                'user_id' => $user->id,
                'attendance_log_id' => $exception->attendanceLog->id,
                'failure_category' => 'attendance_already_recorded',
                'credential_type' => $credentialType,
            ]);

            return $this->result(
                'already_recorded', 'Already Recorded',
                collect($exception->errors())->flatten()->first() ?? 'Attendance has already been recorded.',
                $user, $exception->attendanceLog,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Attendance rejected.';
            $inactive = $message === AccountStatusService::INACTIVE_MESSAGE;
            $security->audit($request, $inactive ? 'inactive' : 'rejected', [
                'user_id' => $user->id,
                'failure_category' => $inactive ? 'account_inactive' : 'attendance_rejected',
                'credential_type' => $credentialType,
            ]);

            return $this->result(
                $inactive ? 'account_inactive' : 'rejected',
                $inactive ? 'Account Inactive' : 'Attendance Not Recorded',
                $message, $user, status: 422,
            );
        }

        $terminal->update(['last_used_at' => now()]);
        $security->audit($request, 'recorded', [
            'user_id' => $log->user_id,
            'attendance_log_id' => $log->id,
            'credential_type' => $credentialType,
        ]);

        return $this->result(
            'recorded', 'Attendance Recorded', 'Attendance was recorded successfully.',
            $user, $log, 201,
        );
    }

    private function person($user): array
    {
        $profile = $user->student ?? $user->instructor ?? $user->nonTeachingStaff;
        $department = $user->student?->course?->department ?? $user->instructor?->department;
        $officeUnit = $user->nonTeachingStaff?->officeUnit;
        $name = $profile && method_exists($profile, 'fullName') ? $profile->fullName() : $user->name;
        $name = $name ?: $user->name;
        $name = preg_replace('/\s+/u', ' ', trim($name));

        $roleName = strtolower((string) $user->primaryRoleName());
        $role = match ($roleName) {
            'student' => 'Student',
            'instructor' => 'Instructor',
            'staff', 'non-teaching staff', 'non_teaching_staff' => 'Non-Teaching Staff',
            default => ucfirst($roleName),
        };

        $details = match ($roleName) {
            'student' => [
                ['label' => 'Department', 'value' => $department?->department_name ?? $department?->department_code ?? 'Not assigned'],
                ['label' => 'Course', 'value' => $user->student?->course?->course_name ?? 'Not assigned'],
                ['label' => 'Year Level', 'value' => $this->yearLevel($user->student?->year_level)],
            ],
            'instructor' => [
                ['label' => 'Department', 'value' => $department?->department_name ?? $department?->department_code ?? 'Not assigned'],
                ['label' => 'Employee ID', 'value' => $user->instructor?->employee_no ?? 'Not assigned'],
                ['label' => 'Position', 'value' => 'Instructor'],
            ],
            default => [
                ['label' => 'Office/Unit', 'value' => $officeUnit?->name ?? 'Not assigned'],
                ['label' => 'Employee ID', 'value' => $user->nonTeachingStaff?->employee_no ?? 'Not assigned'],
                ['label' => 'Position', 'value' => 'Non-Teaching Staff'],
            ],
        };

        return [
            'id' => $user->id,
            'name' => $name,
            'identifier' => $profile?->student_no ?? $profile?->employee_no ?? $user->username,
            'role' => $role,
            'department' => $roleName === 'staff' ? ($officeUnit?->name ?? 'N/A') : ($department?->department_name ?? $department?->department_code ?? 'N/A'),
            'assignment_label' => $roleName === 'staff' ? 'Office/Unit' : 'Department',
            'details' => $details,
            'avatar' => $user->avatar_url,
            'initials' => collect(explode(' ', $name))->filter()->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
        ];
    }

    private function yearLevel(?int $year): string
    {
        if (! $year) {
            return 'Not assigned';
        }

        $suffix = match (true) {
            $year % 100 >= 11 && $year % 100 <= 13 => 'th',
            $year % 10 === 1 => 'st',
            $year % 10 === 2 => 'nd',
            $year % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$year}{$suffix} Year";
    }

    private function result(
        string $code,
        string $title,
        string $message,
        $user = null,
        ?AttendanceLog $log = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'person' => $user ? $this->person($user) : null,
            'attendance' => $log ? [
                'id' => $log->id,
                'status' => $log->status,
                'type' => $log->attendance_type,
                'period' => $log->attendance_period,
                'recorded_time' => $log->scan_time->timezone(config('app.timezone'))->format('M d, Y g:i:s A'),
                'display_time' => $log->scan_time->timezone(config('app.timezone'))->format('g:i A'),
            ] : null,
        ], $status)->header('Cache-Control', 'no-store');
    }
}
