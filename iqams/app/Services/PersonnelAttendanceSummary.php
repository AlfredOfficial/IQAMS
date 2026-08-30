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

    public function __construct(private PersonnelWorkCalendar $calendar) {}

    public function days(User $user, Carbon $from, Carbon $to, bool $includeEmpty = false): Collection
    {
        $logs = AttendanceLog::where('user_id', $user->id)
            ->whereNull('schedule_id')->whereNull('school_event_id')
            ->whereBetween('scan_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('scan_time')->get()->groupBy(fn ($log) => $log->scan_time->toDateString());
        $leavesByDate = $this->approvedLeavesByDate($user, $from, $to);

        $days = collect();
        if ($from->gt($to)) {
            return $days;
        }
        $calendar = $this->calendar->context($user, $from, $to);
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $dateLogs = $logs->get($date->toDateString(), collect());
            $leave = $leavesByDate->get($date->toDateString());
            $exclusionReason = $this->calendar->exclusionReason($date, $calendar);

            if ($dateLogs->isNotEmpty()) {
                $days->push($this->day($date->copy(), $dateLogs));
            } elseif ($leave) {
                $days->push($this->day($date->copy(), $dateLogs, $leave));
            } elseif ($exclusionReason) {
                if ($includeEmpty) {
                    $days->push($this->excludedDay($date->copy(), $exclusionReason));
                }
            } elseif ($includeEmpty) {
                $days->push($this->day($date->copy(), $dateLogs));
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
            'completedPeriods' => $count,
            'progressPercentage' => $count * 25,
            'isIncomplete' => $isIncomplete,
            'isExcluded' => false,
            'exclusionReason' => null,
            'nextPeriod' => $nextPeriod,
            'punctuality' => $leave ? 'Excused' : ($count === 0 ? ($isPast ? 'Absent' : 'Pending') : ($late ? 'Late' : ($early ? 'Early Out' : ($isIncomplete ? 'Incomplete' : ($count < 4 ? 'In Progress' : 'On Time'))))),
        ];
    }

    public function totals(Collection $days): array
    {
        $included = $days->where('isExcluded', false);
        $working = $included->count();
        $present = $included->filter(fn ($day) => ! $day['leave'] && ! in_array($day['status'], ['Absent', 'Not Started'], true))->count();
        $required = $included->whereNull('leave')->count();

        return [
            'workingDays' => $working, 'presentDays' => $present,
            'expectedDays' => $working,
            'excludedDays' => $days->where('isExcluded', true)->count(),
            'absentDays' => $included->where('status', 'Absent')->count(),
            'leaveDays' => $included->whereNotNull('leave')->count(),
            'lateCount' => $included->where('late', true)->count(),
            'earlyOutCount' => $included->where('early', true)->count(),
            'incompleteCount' => $included->where('isIncomplete', true)->count(),
            'totalMinutes' => $included->sum('minutes'),
            'percentage' => $required ? round(($present / $required) * 100, 1) : 0,
        ];
    }

    /**
     * Return approved leave records keyed by each calendar date they cover.
     *
     * Expanding date casts to date-only keys prevents application timezone or
     * time-of-day values from shifting an approved leave to an adjacent day.
     */
    private function approvedLeavesByDate(User $user, Carbon $from, Carbon $to): Collection
    {
        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->startOfDay();

        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->get()
            ->reduce(function (Collection $dates, LeaveRequest $leave) use ($rangeStart, $rangeEnd) {
                $start = Carbon::parse($leave->start_date->toDateString())->max($rangeStart);
                $end = Carbon::parse($leave->end_date->toDateString())->min($rangeEnd);

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dates->put($date->toDateString(), $leave);
                }

                return $dates;
            }, collect());
    }

    private function excludedDay(Carbon $date, string $reason): array
    {
        return array_replace($this->day($date, collect()), [
            'status' => 'Excluded',
            'punctuality' => 'Excluded',
            'notes' => collect([$reason]),
            'isExcluded' => true,
            'exclusionReason' => $reason,
        ]);
    }

    private function pairMinutes(?AttendanceLog $in, ?AttendanceLog $out): int
    {
        return $in && $out && $out->scan_time->gt($in->scan_time) ? $in->scan_time->diffInMinutes($out->scan_time) : 0;
    }
}
