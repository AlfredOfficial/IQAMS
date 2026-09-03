<?php

namespace App\Services;

use App\ValueObjects\ScheduleOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class StudentAttendanceWindow
{
    public function start(ScheduleOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->startsAt;
    }

    public function opensAt(ScheduleOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->opensAt;
    }

    public function presentUntil(ScheduleOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->presentUntil;
    }

    public function endsAt(ScheduleOccurrence $occurrence): CarbonImmutable
    {
        return $occurrence->endsAt;
    }

    public function isOpen(ScheduleOccurrence $occurrence, Carbon $at): bool
    {
        return $at->copy()->timezone(config('app.timezone'))
            ->betweenIncluded($occurrence->opensAt, $occurrence->endsAt);
    }

    public function isPresent(ScheduleOccurrence $occurrence, Carbon $at): bool
    {
        return $at->copy()->timezone(config('app.timezone'))
            ->betweenIncluded($occurrence->opensAt, $occurrence->presentUntil);
    }

    public function status(ScheduleOccurrence $occurrence, Carbon $at): string
    {
        return $this->isPresent($occurrence, $at) ? 'present' : 'late';
    }
}
