<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PersonnelAttendancePages
{
    public function __construct(private PersonnelAttendanceSummary $summary) {}

    public function history(User $user, array $filters): array
    {
        [$from, $to] = $this->boundedRange($filters);
        $days = $this->summary->days($user, $from, $to, false)->reverse()->values();

        if ($status = ($filters['status'] ?? null)) {
            $days = $status === 'Incomplete'
                ? $days->where('isIncomplete', true)->values()
                : $days->filter(fn ($day) => $day['status'] === $status || ($day['summaryStatus'] ?? null) === $status)->values();
        }

        if ($punctuality = ($filters['punctuality'] ?? null)) {
            $days = $days->where('punctuality', $punctuality)->values();
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $daysPaginator = new LengthAwarePaginator(
            $days->forPage($page, $perPage)->values(),
            $days->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        return compact('days', 'from', 'to') + [
            'paginatedDays' => $daysPaginator->getCollection(),
            'daysPaginator' => $daysPaginator,
        ];
    }

    /**
     * Parse and bound user-selected history dates before attendance rows are
     * loaded. The same guard is used by both instructor and staff pages.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function boundedRange(array $filters): array
    {
        $from = $this->date($filters['from'] ?? now()->startOfMonth()->toDateString(), 'from');
        $to = $this->date($filters['to'] ?? now()->toDateString(), 'to');

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        $days = $from->diffInDays($to) + 1;
        $maximum = (int) config('attendance.max_report_days', 366);

        if ($days > $maximum) {
            throw ValidationException::withMessages([
                'to' => "The attendance history range cannot exceed {$maximum} days.",
            ]);
        }

        return [$from, $to];
    }

    private function date(mixed $value, string $field): Carbon
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', (string) $value, config('app.timezone'));
        } catch (\Throwable) {
            $date = false;
        }

        if (! $date || $date->format('Y-m-d') !== (string) $value) {
            throw ValidationException::withMessages([
                $field => 'Enter a valid date in YYYY-MM-DD format.',
            ]);
        }

        return $date->startOfDay();
    }

    public function monthly(User $user, int $requestedMonth, int $requestedYear): array
    {
        $month = max(1, min(12, $requestedMonth));
        $year = max(2000, min(2100, $requestedYear));
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();
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
