<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Services\PersonnelAttendanceSummary;
use App\Services\ScheduleOccurrenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InstructorDashboardController extends Controller
{
    public function index(Request $request, PersonnelAttendanceSummary $summary, ScheduleOccurrenceResolver $occurrences)
    {
        $instructor = $request->user()->instructor?->load('department');
        abort_unless($instructor, 403, 'No instructor profile linked to this account.');
        $today = $summary->day(today(), $this->todayLogs($request));
        $monthDays = $summary->days($request->user(), now()->startOfMonth(), today(), true);
        $totals = $summary->totals($monthDays);
        $at = now(config('app.timezone'));
        $candidateDates = $occurrences->candidateSessionDates($at);
        $candidateDays = collect($candidateDates)
            ->map(fn (Carbon $date) => strtolower($date->format('l')))
            ->all();
        $scheduleCandidates = $instructor->schedules()->with(['subject', 'section'])
            ->whereIn('day', $candidateDays)->orderBy('start_time')->get();
        $occurrenceRows = $scheduleCandidates->flatMap(function (Schedule $schedule) use ($candidateDates, $occurrences): array {
            foreach ($candidateDates as $date) {
                if ($occurrence = $occurrences->forDate($schedule, $date)) {
                    return [['schedule' => $schedule, 'occurrence' => $occurrence]];
                }
            }

            return [];
        });
        $todayDate = $at->copy()->startOfDay();
        $todaySchedules = $occurrenceRows
            ->filter(fn (array $row) => $row['occurrence']->sessionDate->equalTo($todayDate)
                || $at->betweenIncluded($row['occurrence']->startsAt, $row['occurrence']->endsAt))
            ->pluck('schedule')->unique('id')->values();
        $nextRow = $occurrenceRows
            ->filter(fn (array $row) => $row['occurrence']->sessionDate->equalTo($todayDate)
                && $row['occurrence']->startsAt->greaterThan($at))
            ->sortBy(fn (array $row) => $row['occurrence']->startsAt->getTimestamp())
            ->first();
        $nextSchedule = $nextRow['schedule'] ?? null;
        $nextScheduleOccurrence = $nextRow['occurrence'] ?? null;
        if ($nextSchedule && $nextScheduleOccurrence) {
            $nextSchedule->setAttribute('start_time', $nextScheduleOccurrence->startsAt->format('H:i:s'));
            $nextSchedule->setAttribute('end_time', $nextScheduleOccurrence->endsAt->format('H:i:s'));
        }
        $issues = $monthDays->filter(fn ($day) => $day['status'] === 'Absent' || $day['isIncomplete'] || $day['late'] || $day['early'])
            ->reverse()->take(3)->values();
        return view('instructor.dashboard', compact('instructor', 'today', 'totals', 'monthDays', 'todaySchedules', 'nextSchedule', 'nextScheduleOccurrence', 'issues'));
    }

    public function realtime(Request $request, PersonnelAttendanceSummary $summary)
    {
        $today = $summary->day(today(), $this->todayLogs($request));
        $totals = $summary->totals($summary->days($request->user(), now()->startOfMonth(), today(), true));
        return response()->json(['today' => $this->serializeDay($today), 'totals' => $totals, 'updated_at' => now()->format('g:i:s A')]);
    }

    private function todayLogs(Request $request)
    {
        return AttendanceLog::canonical()->where('user_id', $request->user()->id)->whereNull('schedule_id')
            ->whereDate('scan_time', today())->orderBy('scan_time')->get();
    }

    private function serializeDay(array $day): array
    {
        return [
            'status' => $day['status'], 'summary_status' => $day['summaryStatus'], 'punctuality' => $day['punctuality'], 'minutes' => $day['minutes'], 'next_period' => $day['nextPeriod'],
            'completed_periods' => $day['completedPeriods'], 'progress_percentage' => $day['progressPercentage'],
            'events' => $day['events']->map(fn ($log) => $log ? [
                'time' => $log->scan_time->format('g:i A'),
                'punctuality' => str($log->punctuality_status ?? 'on_time')->replace('_', ' ')->title()->toString(),
                'detail' => $this->punctualityDetail($log),
            ] : null),
        ];
    }

    private function punctualityDetail(AttendanceLog $log): string
    {
        $role = 'instructor';
        $stage = config("attendance.personnel_windows.{$role}.{$log->attendance_period}", []);
        $value = $log->punctuality_status ?? 'on_time';
        if ($value === 'late' && isset($stage['on_time_until'])) {
            $deadline = $log->scan_time->copy()->startOfDay()->setTimeFromTimeString($stage['on_time_until']);
            return 'Late by '.ceil($deadline->diffInSeconds($log->scan_time) / 60).' minutes';
        }
        if ($value === 'early_out' && isset($stage['not_early_before'])) {
            $minimum = $log->scan_time->copy()->startOfDay()->setTimeFromTimeString($stage['not_early_before']);
            return 'Early by '.ceil($log->scan_time->diffInSeconds($minimum) / 60).' minutes';
        }
        return 'On Time';
    }
}
