<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;

class InstructorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $instructor = $request->user()->instructor;

        if (! $instructor) {
            abort(403, 'No instructor profile linked to this account.');
        }

        $schedules = $instructor->schedules()->with(['subject', 'section'])->orderBy('start_time')->get();
        $scheduleByDay = $schedules->groupBy('day');
        $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        $todayAttendance = AttendanceLog::whereHas('schedule', function ($query) use ($instructor) {
                $query->where('instructor_id', $instructor->id);
            })
            ->whereDate('scan_time', today())
            ->with(['user', 'schedule.subject'])
            ->latest('scan_time')
            ->get();

        $stats = [
            'todayPresent' => $todayAttendance->where('status', 'present')->count(),
            'todayLate' => $todayAttendance->where('status', 'late')->count(),
            'todayAbsent' => $todayAttendance->where('status', 'absent')->count(),
            'totalSubjects' => $schedules->pluck('subject_id')->unique()->count(),
        ];

        return view('instructor.dashboard', compact('instructor', 'schedules', 'scheduleByDay', 'dayOrder', 'todayAttendance', 'stats'));
    }
}
