<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Services\StudentAbsenceWarningService;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request, StudentAbsenceWarningService $absenceWarnings)
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

        $myAttendance = AttendanceLog::where('user_id', $request->user()->id)
            ->with(['schedule.subject', 'schoolEvent'])
            ->latest('scan_time')
            ->take(10)
            ->get();

        $stats = [
            'present' => AttendanceLog::where('user_id', $request->user()->id)->where('status', 'present')->count(),
            'late' => AttendanceLog::where('user_id', $request->user()->id)->where('status', 'late')->count(),
            'absent' => AttendanceLog::where('user_id', $request->user()->id)->where('status', 'absent')->count(),
            'excused' => AttendanceLog::where('user_id', $request->user()->id)->where('status', 'excused')->count(),
        ];

        $subjectAbsenceWarnings = $absenceWarnings->forStudent($student);

        return view('student.dashboard', compact('student', 'schedules', 'scheduleByDay', 'dayOrder', 'myAttendance', 'stats', 'subjectAbsenceWarnings'));
    }

    public function realtime(Request $request)
    {
        abort_unless($request->user()->student, 403);
        $query = AttendanceLog::where('user_id', $request->user()->id);
        $logs = (clone $query)->with(['schedule.subject', 'schoolEvent'])->latest('scan_time')->take(10)->get();

        return response()->json([
            'stats' => collect(['present', 'late', 'absent', 'excused'])
                ->mapWithKeys(fn ($status) => [$status => (clone $query)->where('status', $status)->count()]),
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
