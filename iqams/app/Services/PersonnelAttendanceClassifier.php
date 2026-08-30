<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PersonnelAttendanceClassifier
{
    public function classify(string $role, string $attendanceType, Carbon $at, string $errorKey = 'scan_time'): array
    {
        $stages = config("attendance.personnel_windows.{$role}", []);
        $period = collect($stages)->reverse()->search(fn (array $stage) => ($stage['type'] ?? null) === $attendanceType && $this->isWithinWindow($stage, $at)
        );

        if ($period === false) {
            throw ValidationException::withMessages([
                $errorKey => 'The selected time and attendance type do not match a configured personnel attendance period.',
            ]);
        }

        return [
            'attendance_period' => $period,
            'punctuality_status' => $this->punctuality($stages[$period], $at),
        ];
    }

    public function punctuality(array $stage, Carbon $at): string
    {
        if (($stage['type'] ?? null) === 'time_in' && isset($stage['on_time_until'])) {
            $deadline = $at->copy()->startOfDay()->setTimeFromTimeString($stage['on_time_until']);

            return $at->greaterThan($deadline) ? 'late' : 'on_time';
        }
        if (($stage['type'] ?? null) === 'time_out' && isset($stage['not_early_before'])) {
            $minimum = $at->copy()->startOfDay()->setTimeFromTimeString($stage['not_early_before']);

            return $at->lessThan($minimum) ? 'early_out' : 'on_time';
        }

        return 'on_time';
    }

    public function isWithinWindow(array $stage, Carbon $at): bool
    {
        $start = $at->copy()->startOfDay()->setTimeFromTimeString($stage['start']);
        $end = $at->copy()->startOfDay()->setTimeFromTimeString($stage['end']);

        return $end->greaterThanOrEqualTo($start)
            ? $at->betweenIncluded($start, $end)
            : $at->greaterThanOrEqualTo($start) || $at->lessThanOrEqualTo($end);
    }
}
