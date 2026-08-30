<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Services\PersonnelAttendanceSummary;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function index(Request $request, PersonnelAttendanceSummary $summary)
    {
        $user = $request->user();
        $staff = $user->nonTeachingStaff?->load('officeUnit');

        abort_unless($staff, 403, 'No non-teaching staff profile is linked to this account.');

        $todayLogs = AttendanceLog::where('user_id', $user->id)
            ->whereNull('schedule_id')
            ->whereDate('scan_time', today())
            ->orderBy('scan_time')
            ->get();

        $today = $summary->day(today(), $todayLogs);
        $monthDays = $summary->days($user, now()->startOfMonth(), today(), true);
        $totals = $summary->totals($monthDays);
        $recentLogs = AttendanceLog::where('user_id', $user->id)
            ->whereNull('schedule_id')
            ->latest('scan_time')
            ->limit(8)
            ->get();

        return view('staff.dashboard', compact('staff', 'today', 'totals', 'recentLogs'));
    }

    public function realtime(Request $request, PersonnelAttendanceSummary $summary)
    {
        $user = $request->user();
        abort_unless($user->nonTeachingStaff, 403);
        $logs = AttendanceLog::where('user_id', $user->id)->whereNull('schedule_id')
            ->whereNull('school_event_id')->latest('scan_time')->limit(8)->get();
        $today = $summary->day(today(), $logs->filter(fn ($log) => $log->scan_time->isToday())->sortBy('scan_time')->values());
        $totals = $summary->totals($summary->days($user, now()->startOfMonth(), today(), true));

        return response()->json([
            'today' => [
                'status' => $today['status'],
                'next_period' => $today['nextPeriod'],
                'completed_periods' => $today['completedPeriods'],
                'progress_percentage' => $today['progressPercentage'],
                'events' => $today['events']->map(fn ($log) => $log ? [
                    'time' => $log->scan_time->format('g:i A'),
                    'punctuality' => str($log->punctuality_status ?? 'on_time')->replace('_', ' ')->title()->toString(),
                ] : null),
            ],
            'totals' => $totals,
            'recent' => $logs->map(fn ($log) => [
                'label' => str($log->attendance_period ?? $log->attendance_type)->replace('_', ' ')->title()->toString(),
                'date' => $log->scan_time->format('F j, Y'), 'time' => $log->scan_time->format('g:i A'),
                'status' => str_replace('_', ' ', $log->punctuality_status ?? $log->status),
            ])->values(),
        ]);
    }
}
