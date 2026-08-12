<?php

namespace App\Services;

use App\Models\AttendanceLog;
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

        $days = collect();
        if ($from->gt($to)) return $days;
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($date->isWeekend()) continue;
            $dateLogs = $logs->get($date->toDateString(), collect());
            if ($includeEmpty || $dateLogs->isNotEmpty()) $days->push($this->day($date->copy(), $dateLogs));
        }
        return $days;
    }

    public function day(Carbon $date, Collection $logs): array
    {
        $events = collect(self::PERIODS)->mapWithKeys(fn ($period) => [$period => $logs->firstWhere('attendance_period', $period)]);
        $count = $events->filter()->count();
        $isPast = $date->isBefore(today());
        $late = $events->contains(fn ($log) => $log?->punctuality_status === 'late');
        $early = $events->contains(fn ($log) => $log?->punctuality_status === 'early_out');
        $status = match ($count) {
            0 => $isPast ? 'Absent' : 'Not Started',
            1 => 'Morning In Recorded',
            2 => 'Morning Complete',
            3 => 'Afternoon In Recorded',
            default => 'Completed',
        };
        $minutes = $this->pairMinutes($events['morning_in'], $events['lunch_out']) + $this->pairMinutes($events['afternoon_in'], $events['final_out']);
        $notes = collect([$late ? 'Late Arrival' : null, $early ? 'Early Out' : null])->filter()->values();

        return compact('date', 'events', 'status', 'minutes', 'late', 'early', 'notes') + [
            'isIncomplete' => $isPast && $count > 0 && $count < 4,
            'nextPeriod' => self::PERIODS[$count] ?? null,
            'punctuality' => $count === 0 ? ($isPast ? 'Absent' : 'Pending') : ($late ? 'Late' : ($early ? 'Early Out' : ($count < 4 ? 'In Progress' : 'On Time'))),
        ];
    }

    public function totals(Collection $days): array
    {
        $working = $days->count();
        $present = $days->whereNotIn('status', ['Absent', 'Not Started'])->count();
        return [
            'workingDays' => $working, 'presentDays' => $present,
            'absentDays' => $days->where('status', 'Absent')->count(),
            'lateCount' => $days->where('late', true)->count(),
            'earlyOutCount' => $days->where('early', true)->count(),
            'incompleteCount' => $days->where('isIncomplete', true)->count(),
            'totalMinutes' => $days->sum('minutes'),
            'percentage' => $working ? round(($present / $working) * 100, 1) : 0,
        ];
    }

    private function pairMinutes(?AttendanceLog $in, ?AttendanceLog $out): int
    {
        return $in && $out && $out->scan_time->gt($in->scan_time) ? $in->scan_time->diffInMinutes($out->scan_time) : 0;
    }
}
