<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Services\StudentAbsenceWarningService;
use App\Services\StudentAttendanceSummary;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(
        Request $request,
        StudentAbsenceWarningService $absenceWarnings,
        StudentAttendanceSummary $attendanceSummary,
    )
    {
        $student = $request->user()->student?->load(['course.department', 'section']);

        if (! $student) {
            abort(403, 'No student profile linked to this account.');
        }

        $schedules = $student->section
            ? $student->section->schedules()->with(['subject', 'instructor'])->orderBy('start_time')->get()
            : collect();

        $scheduleByDay = $schedules->groupBy('day');

        $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $myAttendance = AttendanceLog::canonical()->where('user_id', $request->user()->id)
            ->with(['schedule.subject', 'schoolEvent'])
            ->latest('scan_time')
            ->take(10)
            ->get();

        $subjectAbsenceWarnings = $absenceWarnings->forStudent($student);
        $summary = $attendanceSummary->forStudent($student);
        $stats = collect(['present', 'late', 'absent', 'excused'])
            ->mapWithKeys(fn ($status) => [$status => $summary[$status]])
            ->all();

        return view('student.dashboard', compact('student', 'schedules', 'scheduleByDay', 'dayOrder', 'myAttendance', 'stats', 'summary', 'subjectAbsenceWarnings'));
    }

    public function realtime(Request $request, StudentAttendanceSummary $attendanceSummary)
    {
        $student = $request->user()->student;
        abort_unless($student, 403);
        $query = AttendanceLog::canonical()->where('user_id', $request->user()->id);
        $logs = (clone $query)->with(['schedule.subject', 'schoolEvent'])->latest('scan_time')->take(10)->get();

        $summary = $attendanceSummary->forStudent($student);

        return response()->json([
            'stats' => collect(['present', 'late', 'absent', 'excused'])
                ->mapWithKeys(fn ($status) => [$status => $summary[$status]]),
            'summary' => $summary,
            'recent' => $logs->map(fn ($log) => [
                'code' => $log->schoolEvent ? 'SCHOOL EVENT' : ($log->schedule?->subject?->subject_code ?? '—'),
                'title' => $log->schoolEvent?->title ?? $log->schedule?->subject?->subject_name ?? 'Attendance record',
                'status' => $log->status, 'date' => $log->scan_time->format('F j, Y'),
                'time' => $log->scan_time->format('g:i A'),
                'type' => str_replace('_', ' ', $log->attendance_type),
            ])->values(),
        ]);
    }
}
