<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Services\PersonnelAttendanceSummary;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function index(Request $request, PersonnelAttendanceSummary $summary)
    {
        $user = $request->user()->load([
            'nonTeachingStaff:id,user_id,office_unit_id,employee_no,name_prefix,first_name,middle_name,last_name,name_suffix',
            'nonTeachingStaff.officeUnit:id,name',
        ]);
        $staff = $user->nonTeachingStaff;

        abort_unless($staff, 403, 'No non-teaching staff profile is linked to this account.');

        $month = $summary->dashboardMonth($user, now(config('app.timezone')));
        $today = $month['today'];
        $totals = $month['totals'];
        $recentLogs = AttendanceLog::canonical()->where('user_id', $user->id)
            ->whereNull('schedule_id')
            ->select(['id', 'attendance_period', 'attendance_type', 'scan_time', 'status', 'punctuality_status'])
            ->latest('scan_time')
            ->limit(8)
            ->get();

        return view('staff.dashboard', compact('staff', 'today', 'totals', 'recentLogs'));
    }

    public function realtime(Request $request, PersonnelAttendanceSummary $summary)
    {
        $user = $request->user();
        abort_unless($user->nonTeachingStaff, 403);
        $logs = AttendanceLog::canonical()->where('user_id', $user->id)->whereNull('schedule_id')
            ->whereNull('school_event_id')
            ->select(['id', 'attendance_period', 'attendance_type', 'scan_time', 'status', 'punctuality_status'])
            ->latest('scan_time')->limit(8)->get();
        $month = $summary->dashboardMonth($user, now(config('app.timezone')));
        $today = $month['today'];
        $totals = $month['totals'];

        return response()->json([
            'today' => [
                'status' => $today['status'],
                'summary_status' => $today['summaryStatus'],
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
