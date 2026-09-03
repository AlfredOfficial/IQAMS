<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentAbsenceService
{
    public function __construct(
        private ScheduleOccurrenceResolver $occurrences,
        private SchoolEventResolver $events,
        private AttendanceAbsenceWriter $writer,
    ) {}

    public function markDue(?Carbon $at = null): int
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));
        $created = 0;
        $candidateDates = $this->occurrences->candidateSessionDates($at);
        $candidateDays = collect($candidateDates)
            ->map(fn (Carbon $date) => strtolower($date->format('l')))
            ->all();

        $schedules = Schedule::active()->whereIn('day', $candidateDays)->get()
            ->flatMap(function (Schedule $schedule) use ($candidateDates): array {
                foreach ($candidateDates as $date) {
                    if ($occurrence = $this->occurrences->forDate($schedule, $date)) {
                        return [$occurrence];
                    }
                }

                return [];
            })
            ->filter(fn ($occurrence) => $at->greaterThan($occurrence->presentUntil));

        foreach ($schedules as $occurrence) {
            $event = $this->events->affectingOccurrence($occurrence);

            $created += DB::transaction(fn () => $this->writer->forSchedule($occurrence, $event));
        }

        return $created;
    }
}
