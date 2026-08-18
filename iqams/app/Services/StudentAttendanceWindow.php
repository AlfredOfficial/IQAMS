<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Carbon;

class StudentAttendanceWindow
{
    public function start(Schedule $schedule, Carbon $date): Carbon
    {
        return $date->copy()
            ->timezone(config('app.timezone'))
            ->startOfDay()
            ->setTimeFromTimeString($schedule->start_time);
    }

    public function opensAt(Schedule $schedule, Carbon $date): Carbon
    {
        return $this->start($schedule, $date)
            ->subMinutes(config('attendance.early_scan_minutes'));
    }

    public function presentUntil(Schedule $schedule, Carbon $date): Carbon
    {
        return $this->start($schedule, $date)
            ->addMinutes(config('attendance.present_grace_minutes'))
            ->endOfMinute();
    }

    public function endsAt(Schedule $schedule, Carbon $date): Carbon
    {
        $start = $this->start($schedule, $date);
        $end = $date->copy()
            ->timezone(config('app.timezone'))
            ->startOfDay()
            ->setTimeFromTimeString($schedule->end_time)
            ->endOfMinute();

        return $end->lessThanOrEqualTo($start) ? $end->addDay() : $end;
    }

    public function isOpen(Schedule $schedule, Carbon $at): bool
    {
        return $at->betweenIncluded($this->opensAt($schedule, $at), $this->endsAt($schedule, $at));
    }

    public function isPresent(Schedule $schedule, Carbon $at): bool
    {
        return $at->betweenIncluded($this->opensAt($schedule, $at), $this->presentUntil($schedule, $at));
    }

    public function status(Schedule $schedule, Carbon $at): string
    {
        return $this->isPresent($schedule, $at) ? 'present' : 'late';
    }
}
