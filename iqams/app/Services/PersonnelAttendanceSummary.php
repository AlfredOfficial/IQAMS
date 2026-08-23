<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PersonnelAttendanceSummary
{
    public const PERIODS = ['morning_in', 'lunch_out', 'afternoon_in', 'final_out'];

    public function days(User $user, Carbon $from, Carbon $to, bool $includeEmpty = false): Collection
    {
        $logs = AttendanceLog::where('user_id', $user->id)->whereNull('schedule_id')
            ->whereBetween('scan_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('scan_time')->get()->groupBy(fn ($log) => $log->scan_time->toDateString());
        $leaves = LeaveRequest::where('user_id', $user->id)->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get();

        $days = collect();
        if ($from->gt($to)) {
            return $days;
        }
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }
            $dateLogs = $logs->get($date->toDateString(), collect());
            $leave = $leaves->first(fn ($item) => $date->betweenIncluded($item->start_date, $item->end_date));
            if ($includeEmpty || $dateLogs->isNotEmpty() || $leave) {
                $days->push($this->day($date->copy(), $dateLogs, $leave));
            }
        }

        return $days;
    }

    public function day(Carbon $date, Collection $logs, ?LeaveRequest $leave = null): array
    {
        $events = collect(self::PERIODS)->mapWithKeys(fn ($period) => [$period => $logs->firstWhere('attendance_period', $period)]);
        $count = $events->filter()->count();
        $isPast = $date->isBefore(today());
        $latestRecordedIndex = collect(self::PERIODS)
            ->keys()
            ->filter(fn ($index) => $events[self::PERIODS[$index]])
            ->max();
        $hasSkippedPeriod = $latestRecordedIndex !== null
            && collect(self::PERIODS)
                ->take($latestRecordedIndex + 1)
                ->contains(fn ($period) => ! $events[$period]);
        $isIncomplete = ! $leave && $count > 0 && ($hasSkippedPeriod || ($isPast && $count < count(self::PERIODS)));
        $late = $events->contains(fn ($log) => $log?->punctuality_status === 'late');
        $early = $events->contains(fn ($log) => $log?->punctuality_status === 'early_out');
        $status = match (true) {
            $count === 0 => $isPast ? 'Absent' : 'Not Started',
            $count === count(self::PERIODS) => 'Completed',
            (bool) $events['final_out'] => 'Final Out Recorded',
            (bool) $events['afternoon_in'] => 'Afternoon In Recorded',
            (bool) $events['lunch_out'] => 'Morning Complete',
            default => 'Morning In Recorded',
        };
        if ($leave) {
            $status = $leave->leave_type === 'sick' ? 'Sick Leave' : 'On Leave';
        }
        $minutes = $leave ? 0 : $this->pairMinutes($events['morning_in'], $events['lunch_out']) + $this->pairMinutes($events['afternoon_in'], $events['final_out']);
        $notes = collect([
            $leave && $count > 0 ? 'Attendance exists during approved leave' : null,
            ! $leave && $hasSkippedPeriod ? 'Missing earlier attendance scan' : null,
            ! $leave && $late ? 'Late Arrival' : null,
            ! $leave && $early ? 'Early Out' : null,
        ])->filter()->values();

        $nextPeriod = $leave || $latestRecordedIndex === count(self::PERIODS) - 1
            ? null
            : self::PERIODS[($latestRecordedIndex ?? -1) + 1];

        return compact('date', 'events', 'status', 'minutes', 'late', 'early', 'notes', 'leave') + [
            'isIncomplete' => $isIncomplete,
            'nextPeriod' => $nextPeriod,
            'punctuality' => $leave ? 'Excused' : ($count === 0 ? ($isPast ? 'Absent' : 'Pending') : ($late ? 'Late' : ($early ? 'Early Out' : ($isIncomplete ? 'Incomplete' : ($count < 4 ? 'In Progress' : 'On Time'))))),
        ];
    }

    public function totals(Collection $days): array
    {
        $working = $days->count();
        $present = $days->whereNotIn('status', ['Absent', 'Not Started', 'On Leave', 'Sick Leave'])->count();
        $required = $days->whereNotIn('status', ['On Leave', 'Sick Leave'])->count();

        return [
            'workingDays' => $working, 'presentDays' => $present,
            'absentDays' => $days->where('status', 'Absent')->count(),
            'leaveDays' => $days->whereIn('status', ['On Leave', 'Sick Leave'])->count(),
            'lateCount' => $days->where('late', true)->count(),
            'earlyOutCount' => $days->where('early', true)->count(),
            'incompleteCount' => $days->where('isIncomplete', true)->count(),
            'totalMinutes' => $days->sum('minutes'),
            'percentage' => $required ? round(($present / $required) * 100, 1) : 0,
        ];
    }

    private function pairMinutes(?AttendanceLog $in, ?AttendanceLog $out): int
    {
        return $in && $out && $out->scan_time->gt($in->scan_time) ? $in->scan_time->diffInMinutes($out->scan_time) : 0;
    }
}
