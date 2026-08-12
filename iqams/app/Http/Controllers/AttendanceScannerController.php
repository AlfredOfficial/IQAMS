<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Services\QrAttendanceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceScannerController extends Controller
{
    public function index(): View
    {
        return view('attendance-scanner.index', [
            'recentAttendance' => $this->recentAttendance(),
        ]);
    }

    public function store(Request $request, QrAttendanceService $attendance): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            'scanner_location' => ['nullable', 'string', 'max:255'],
        ], [
            'qr_code.required' => 'The scanner did not send a QR value.',
            'qr_code.min' => 'The scanned QR value is too short.',
            'qr_code.regex' => 'The scanned QR value has an invalid format.',
        ]);

        try {
            $log = $attendance->record(
                $validated['qr_code'],
                $validated['scanner_location'] ?? null,
            );
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Attendance could not be saved because of a database error. Please scan again or contact the administrator.',
            ], 500);
        }

        $formatted = $this->formatLog($log);

        return response()->json([
            'message' => $formatted['name'].' - '.($formatted['subject'] ?? $formatted['attendance_type_label']).' - '.$formatted['status_label'],
            'attendance' => $formatted,
            'recent_attendance' => $this->recentAttendance(),
        ], 201);
    }

    private function recentAttendance(): array
    {
        return AttendanceLog::with($this->relations())
            ->latest('scan_time')
            ->limit(10)
            ->get()
            ->map(fn (AttendanceLog $log) => $this->formatLog($log))
            ->values()
            ->all();
    }

    private function formatLog(AttendanceLog $log): array
    {
        $log->loadMissing($this->relations());
        $user = $log->user;
        $student = $user?->student;
        $instructor = $user?->instructor;
        $staff = $user?->nonTeachingStaff;
        $role = strtolower($user?->role?->role_name ?? 'unknown');
        $department = $instructor?->department?->department_name
            ?? $student?->course?->department?->department_name
            ?? ($role === 'staff' ? 'Administration' : null);
        $identifier = $student?->student_no
            ?? $instructor?->employee_no
            ?? $staff?->employee_no
            ?? $user?->username;
        $schedule = $log->schedule;

        return [
            'id' => $log->id,
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'name' => $user?->name ?? 'Unknown user',
            'avatar' => $user?->avatar_url,
            'initials' => collect(explode(' ', $user?->name ?? '?'))->filter()->take(2)
                ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'role' => match ($role) {
                'student' => 'Student',
                'instructor' => 'Teaching Personnel',
                'staff' => 'Non-teaching Personnel',
                default => ucfirst($role),
            },
            'department' => $department,
            'course_section' => $student
                ? collect([$student->course?->course_code, $student->section?->section_name])->filter()->implode(' / ')
                : null,
            'subject' => $schedule?->subject?->subject_name,
            'subject_code' => $schedule?->subject?->subject_code,
            'schedule' => $schedule
                ? ucfirst($schedule->day).' · '.Carbon::parse($schedule->start_time)->format('g:i A').'–'.Carbon::parse($schedule->end_time)->format('g:i A').' · '.$schedule->room
                : null,
            'attendance_type' => $log->attendance_type,
            'attendance_type_label' => $this->attendanceLabel($log),
            'status' => $log->status,
            'status_label' => ucfirst($log->status),
            'punctuality_status' => $log->punctuality_status,
            'punctuality_label' => str($log->punctuality_status ?? 'on_time')->replace('_', ' ')->title()->toString(),
            'scan_time' => $log->scan_time->format('M d, Y g:i:s A'),
        ];
    }

    private function relations(): array
    {
        return [
            'user.role',
            'user.student.section',
            'user.student.course.department',
            'user.instructor.department',
            'user.nonTeachingStaff',
            'schedule.subject',
            'schedule.section',
        ];
    }

    private function attendanceLabel(AttendanceLog $log): string
    {
        if ($log->attendance_period) {
            $role = strtolower($log->user?->role?->role_name ?? '');
            $label = config("attendance.personnel_windows.{$role}.{$log->attendance_period}.label");

            if ($label) {
                return $label;
            }
        }

        return str_replace('_', ' ', ucfirst($log->attendance_type));
    }
}
