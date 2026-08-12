<?php

namespace App\Http\Controllers;

use App\Services\PersonnelAttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InstructorAttendanceController extends Controller
{
    public function history(Request $request, PersonnelAttendanceSummary $summary)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('to', now()->toDateString()));
        $days = $summary->days($request->user(), $from, $to, false)->reverse()->values();
        if ($status = $request->input('status')) {
            $days = $status === 'Incomplete' ? $days->where('isIncomplete', true)->values() : $days->where('status', $status)->values();
        }
        if ($punctuality = $request->input('punctuality')) $days = $days->where('punctuality', $punctuality)->values();
        return view('instructor.history', compact('days', 'from', 'to'));
    }

    public function summary(Request $request, PersonnelAttendanceSummary $service)
    {
        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $from = Carbon::create($year, $month, 1);
        $to = $from->isFuture() ? $from->copy()->subDay() : $from->copy()->endOfMonth()->min(today());
        $days = $service->days($request->user(), $from, $to, true);
        $totals = $service->totals($days);
        return view('instructor.summary', compact('days', 'totals', 'month', 'year'));
    }

    public function issues(Request $request, PersonnelAttendanceSummary $service)
    {
        $from = now()->startOfMonth();
        $days = $service->days($request->user(), $from, today(), true)
            ->filter(fn ($day) => $day['status'] === 'Absent' || $day['isIncomplete'] || $day['late'] || $day['early'])->reverse()->values();
        return view('instructor.issues', compact('days'));
    }

    public function schedule(Request $request)
    {
        $instructor = $request->user()->instructor;
        abort_unless($instructor, 403);
        $days = $instructor->schedules()->with(['subject', 'section'])->orderBy('start_time')->get()->groupBy('day');
        return view('instructor.schedule', compact('instructor', 'days'));
    }
}
