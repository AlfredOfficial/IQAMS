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
        $staff = $user->nonTeachingStaff?->load('department');

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
}
