<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\LocalTimeRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PersonnelAttendanceSummary
{
    public const PERIODS = ['morning_in', 'lunch_out', 'afternoon_in', 'final_out'];

    public function __construct(
        private PersonnelWorkCalendar $calendar,
        private AttendanceSummaryCache $cache,
    ) {}

    public function days(User $user, Carbon $from, Carbon $to, bool $includeEmpty = false): Collection
    {
        if ($from->gt($to)) {
            return collect();
        }

        $maximum = (int) config('attendance.max_report_days', 366);
        if ($from->diffInDays($to) + 1 > $maximum) {
            throw ValidationException::withMessages([
                'to' => "The attendance summary range cannot exceed {$maximum} days.",
            ]);
        }

        return $this->cache->rememberPersonnel(
            $user->id,
            $from,
            $to,
            $includeEmpty,
            fn (): Collection => $this->calculateDays($user, $from, $to, $includeEmpty),
        );
    }

    /**
     * Build the current personnel dashboard from one monthly attendance query.
     * The daily and period totals are deliberately calculated through the same
     * rule methods used by the history pages.
     */
    public function dashboardMonth(User $user, Carbon $today): array
    {
        $today = $today->copy()->timezone(config('app.timezone'))->startOfDay();
        $from = $today->copy()->startOfMonth();
        $to = $today->copy();

        return $this->cache->rememberPersonnelDashboard(
            $user->id,
            $from,
            $to,
            fn (): array => $this->calculateDashboardMonth($user, $from, $to),
        );
    }

    private function calculateDays(User $user, Carbon $from, Carbon $to, bool $includeEmpty): Collection
    {
        return $this->calculatePeriod($user, $from, $to, $includeEmpty)['days'];
    }

    /**
     * Calculate the dashboard payload while sharing the monthly attendance
     * rows between today's progress and the monthly totals.
     */
    private function calculateDashboardMonth(User $user, Carbon $from, Carbon $to): array
    {
        $period = $this->calculatePeriod($user, $from, $to, true, $to);
        $days = $period['days'];

        return [
            'today' => $period['today'],
            'monthDays' => $days,
            'totals' => $this->totals($days),
        ];
    }

    /**
     * @return array{days: Collection, today: array|null}
     */
    private function calculatePeriod(User $user, Carbon $from, Carbon $to, bool $includeEmpty, ?Carbon $dashboardToday = null): array
    {
        [$start, $end] = LocalTimeRange::dates($from, $to);
        $logs = AttendanceLog::canonical()->where('user_id', $user->id)
            ->whereNull('schedule_id')->whereNull('school_event_id')
            ->where('scan_time', '>=', $start)
            ->where('scan_time', '<', $end)
            ->orderBy('scan_time')
            ->get(['id', 'attendance_period', 'scan_time', 'punctuality_status'])
            ->groupBy(fn ($log) => $log->scan_time->copy()->timezone(config('app.timezone'))->toDateString());
        $leavesByDate = $this->approvedLeavesByDate($user, $from, $to);

        $days = collect();
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

        return [
            'days' => $days,
            'today' => $dashboardToday
                ? $this->day($dashboardToday, $logs->get($dashboardToday->toDateString(), collect()))
                : null,
        ];
    }

    public function day(Carbon $date, Collection $logs, ?LeaveRequest $leave = null): array
    {
        $events = collect(self::PERIODS)->mapWithKeys(fn ($period) => [$period => $logs->firstWhere('attendance_period', $period)]);
        $count = $events->filter()->count();
        $isPast = $date->isBefore(today());
        $isToday = $date->isToday();
        $latestRecordedIndex = collect(self::PERIODS)
            ->keys()
            ->filter(fn ($index) => $events[self::PERIODS[$index]])
            ->max();
        $hasSkippedPeriod = $latestRecordedIndex !== null
            && collect(self::PERIODS)
                ->take($latestRecordedIndex + 1)
                ->contains(fn ($period) => ! $events[$period]);
        $isIncomplete = ! $leave && $isPast && $count > 0 && $count < count(self::PERIODS);
        $isInProgress = ! $leave && $isToday && $count > 0 && $count < count(self::PERIODS);
        $late = $events['morning_in']?->punctuality_status === 'late';
        $early = $events['final_out']?->punctuality_status === 'early_out';
        $status = match (true) {
            $count === 0 => $isPast ? 'Absent' : 'Not Started',
            $count === count(self::PERIODS) => 'Present',
            $isIncomplete => 'Incomplete',
            default => 'In Progress',
        };
        if ($leave) {
            $status = 'On Leave';
        }
        $summaryStatus = $leave
            ? 'On Leave'
            : ($count > 0 ? 'Present' : ($isPast ? 'Absent' : null));
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

        return compact('date', 'events', 'status', 'summaryStatus', 'minutes', 'late', 'early', 'notes', 'leave') + [
            'completedPeriods' => $count,
            'progressPercentage' => $count * 25,
            'isIncomplete' => $isIncomplete,
            'isInProgress' => $isInProgress,
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
        // Keep detailed four-period accounting intact. A partial day is
        // surfaced as Present through summaryStatus, but remains Incomplete
        // in period-completion totals and attendance-rate calculations.
        $present = $included->where('status', 'Present')->count();
        $absent = $included->where('status', 'Absent')->count();
        $ratedDays = $present + $absent;

        return [
            'workingDays' => $working, 'presentDays' => $present,
            'expectedDays' => $working,
            'excludedDays' => $days->where('isExcluded', true)->count(),
            'absentDays' => $absent,
            'leaveDays' => $included->whereNotNull('leave')->count(),
            'lateCount' => $included->where('late', true)->count(),
            'earlyOutCount' => $included->where('early', true)->count(),
            'incompleteCount' => $included->where('isIncomplete', true)->count(),
            'inProgressCount' => $included->where('isInProgress', true)->count(),
            'totalMinutes' => $included->sum('minutes'),
            'percentage' => $ratedDays ? round(($present / $ratedDays) * 100, 2) : 0,
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
            // The upper bound is exclusive so this remains correct for both
            // native DATE columns and legacy datetime-like values.
            ->where('start_date', '<', $rangeEnd->copy()->addDay()->toDateString())
            ->where('end_date', '>=', $rangeStart->toDateString())
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
