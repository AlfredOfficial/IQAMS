<?php

namespace App\Services;

use App\Models\Schedule;
use App\ValueObjects\ScheduleOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ScheduleOccurrenceResolver
{
    public function forDate(Schedule $schedule, Carbon $sessionDate): ?ScheduleOccurrence
    {
        $date = CarbonImmutable::instance($sessionDate->copy()->timezone(config('app.timezone'))->startOfDay());

        if (strtolower($date->format('l')) !== strtolower((string) $schedule->day)) {
            return null;
        }

        $startsAt = $this->atTime($date, $schedule->start_time);
        $endsAt = $this->atTime($date, $schedule->end_time);

        if ($endsAt->equalTo($startsAt)) {
            throw new InvalidArgumentException('A schedule occurrence cannot have equal start and end times.');
        }

        $overnight = $endsAt->lessThanOrEqualTo($startsAt);

        if ($overnight) {
            $endsAt = $endsAt->addDay();
        }

        return new ScheduleOccurrence(
            schedule: $schedule,
            sessionDate: $date,
            opensAt: $startsAt->subMinutes((int) config('attendance.early_scan_minutes')),
            startsAt: $startsAt,
            presentUntil: $startsAt->addMinutes((int) config('attendance.present_grace_minutes'))->endOfMinute(),
            endsAt: $endsAt->endOfMinute(),
            overnight: $overnight,
        );
    }

    public function resolveAt(Schedule $schedule, Carbon $instant): ?ScheduleOccurrence
    {
        $at = CarbonImmutable::instance($instant->copy()->timezone(config('app.timezone')));

        foreach ([$at->startOfDay(), $at->subDay()->startOfDay()] as $sessionDate) {
            $occurrence = $this->forDate($schedule, Carbon::instance($sessionDate));

            if ($occurrence && $at->betweenIncluded($occurrence->opensAt, $occurrence->endsAt)) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * @return array<int, Carbon>
     */
    public function candidateSessionDates(Carbon $instant): array
    {
        $at = $instant->copy()->timezone(config('app.timezone'))->startOfDay();

        return [$at, $at->copy()->subDay()];
    }

    private function atTime(CarbonImmutable $date, mixed $time): CarbonImmutable
    {
        return $date->setTimeFromTimeString((string) $time);
    }
}
