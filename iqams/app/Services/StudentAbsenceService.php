<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $occurrences = Schedule::active()->whereIn('day', $candidateDays)->get()
            ->flatMap(function (Schedule $schedule) use ($candidateDates): array {
                foreach ($candidateDates as $date) {
                    if ($occurrence = $this->occurrences->forDate($schedule, $date)) {
                        return [$occurrence];
                    }
                }

                return [];
            })
            ->filter(fn ($occurrence) => $at->greaterThan($occurrence->presentUntil));

        $eventContext = $this->eventContext($at, $occurrences);

        foreach ($occurrences as $occurrence) {
            $event = $this->events->affectingOccurrence($occurrence, $eventContext);

            $created += DB::transaction(fn () => $this->writer->forSchedule($occurrence, $event));
        }

        return $created;
    }

    private function eventContext(Carbon $at, Collection $occurrences): ?SchoolEventContext
    {
        if ($occurrences->isEmpty()) {
            return null;
        }

        $from = $at->copy();
        $to = $at->copy();

        foreach ($occurrences as $occurrence) {
            $from = $occurrence->startsAt->lt($from) ? $occurrence->startsAt->copy() : $from;
            $to = $occurrence->endsAt->gt($to) ? $occurrence->endsAt->copy() : $to;
        }

        return $this->events->context($from, $to);
    }
}
