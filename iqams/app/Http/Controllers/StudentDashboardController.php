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
            ->with('schedule.subject')
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
}
