<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class PersonnelAttendancePages
{
    public function __construct(private PersonnelAttendanceSummary $summary) {}

    public function history(User $user, array $filters): array
    {
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth()->toDateString());
        $to = Carbon::parse($filters['to'] ?? now()->toDateString());
        $days = $this->summary->days($user, $from, $to, false)->reverse()->values();

        if ($status = ($filters['status'] ?? null)) {
            $days = $status === 'Incomplete'
                ? $days->where('isIncomplete', true)->values()
                : $days->where('status', $status)->values();
        }

        if ($punctuality = ($filters['punctuality'] ?? null)) {
            $days = $days->where('punctuality', $punctuality)->values();
        }

        return compact('days', 'from', 'to');
    }

    public function monthly(User $user, int $requestedMonth, int $requestedYear): array
    {
        $month = max(1, min(12, $requestedMonth));
        $year = max(2000, min(2100, $requestedYear));
        $from = Carbon::create($year, $month, 1);
        $to = $from->isFuture() ? $from->copy()->subDay() : $from->copy()->endOfMonth()->min(today());
        $days = $this->summary->days($user, $from, $to, true);
        $totals = $this->summary->totals($days);

        return compact('days', 'totals', 'month', 'year');
    }

    public function issues(User $user): array
    {
        $days = $this->summary->days($user, now()->startOfMonth(), today(), true)
            ->filter(fn ($day) => ! $day['isExcluded']
                && ($day['status'] === 'Absent' || $day['isIncomplete'] || $day['late'] || $day['early']))
            ->reverse()
            ->values();

        return compact('days');
    }
}
