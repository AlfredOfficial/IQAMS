<?php

namespace App\ValueObjects;

use App\Models\Schedule;
use Carbon\CarbonImmutable;

final readonly class ScheduleOccurrence
{
    public function __construct(
        public Schedule $schedule,
        public CarbonImmutable $sessionDate,
        public CarbonImmutable $opensAt,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $presentUntil,
        public CarbonImmutable $endsAt,
        public bool $overnight,
    ) {}
}
