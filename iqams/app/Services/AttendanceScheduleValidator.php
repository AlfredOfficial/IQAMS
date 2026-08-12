<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceScheduleValidator
{
    public const DENIED_MESSAGE = 'Attendance denied. You do not have a scheduled class session at this time.';

    /**
     * Ensure a student's attendance belongs to their section and occurs
     * during the selected class session. Non-student attendance is unchanged.
     */
    public function validate(User $user, Schedule $schedule, Carbon $occurredAt): void
    {
        $student = $user->student;

        if (! $student) {
            return;
        }

        if ((int) $student->section_id !== (int) $schedule->section_id
            || ! $this->isWithinSession($schedule, $occurredAt)) {
            throw ValidationException::withMessages([
                'schedule_id' => self::DENIED_MESSAGE,
            ]);
        }
    }

    private function isWithinSession(Schedule $schedule, Carbon $occurredAt): bool
    {
        $time = $occurredAt->copy()->timezone(config('app.timezone'));
        $start = $time->copy()->startOfDay()->setTimeFromTimeString($schedule->start_time);
        $end = $time->copy()->startOfDay()->setTimeFromTimeString($schedule->end_time);
        $scheduleDay = strtolower($schedule->day);

        if ($end->greaterThan($start)) {
            return strtolower($time->format('l')) === $scheduleDay
                && $time->betweenIncluded($start, $end);
        }

        // An end time at or before the start time represents a session that
        // continues into the following calendar day.
        if (strtolower($time->format('l')) === $scheduleDay) {
            return $time->greaterThanOrEqualTo($start);
        }

        $previousDay = $time->copy()->subDay();

        return strtolower($previousDay->format('l')) === $scheduleDay
            && $time->lessThanOrEqualTo($end);
    }
}
