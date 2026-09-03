<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use App\ValueObjects\ScheduleOccurrence;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceScheduleValidator
{
    public const DENIED_MESSAGE = 'Attendance denied. You do not have a scheduled class session at this time.';

    public function __construct(
        private ScheduleOccurrenceResolver $occurrences,
        private SchoolEventResolver $events,
    ) {}

    /**
     * Ensure a student's attendance belongs to their section and occurs
     * during the selected class session. Non-student attendance is unchanged.
     */
    public function validate(User $user, Schedule $schedule, Carbon $occurredAt): ?ScheduleOccurrence
    {
        $student = $user->student;

        if (! $student) {
            return null;
        }

        $occurrence = $this->occurrences->resolveAt($schedule, $occurredAt);

        if ((int) $student->section_id !== (int) $schedule->section_id
            || ! $occurrence
            || $this->events->affectingOccurrence($occurrence)) {
            throw ValidationException::withMessages([
                'schedule_id' => self::DENIED_MESSAGE,
            ]);
        }

        return $occurrence;
    }
}
