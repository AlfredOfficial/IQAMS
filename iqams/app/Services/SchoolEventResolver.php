<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SchoolEventResolver
{
    public function activeAttendanceEvent(Student $student, Carbon $at): ?SchoolEvent
    {
        return $this->publishedNear($at)
            ->where('attendance_mode', 'event_attendance')
            ->filter(fn (SchoolEvent $event) => $this->targetsStudent($event, $student)
                && $at->betweenIncluded($event->starts_at->copy()->subMinutes(config('attendance.early_scan_minutes')), $event->ends_at))
            ->sortBy(fn (SchoolEvent $event) => $event->starts_at->getTimestamp())
            ->first();
    }

    public function affectingSchedule(Schedule $schedule, Carbon $date): ?SchoolEvent
    {
        $start = $date->copy()->startOfDay()->setTimeFromTimeString($schedule->start_time);
        $end = $date->copy()->startOfDay()->setTimeFromTimeString($schedule->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $this->publishedNear($start)
            ->whereIn('attendance_mode', ['cancelled', 'event_attendance'])
            ->filter(fn (SchoolEvent $event) => $this->targetsSchedule($event, $schedule)
                && $event->starts_at->lessThan($end) && $event->ends_at->greaterThan($start))
            ->sortBy('starts_at')
            ->first();
    }

    public function targetsStudent(SchoolEvent $event, Student $student): bool
    {
        if ($event->target_scope === 'school') {
            return true;
        }
        $event->loadMissing('targets');
        if ($event->target_scope === 'sections') {
            return $event->targets->contains('section_id', $student->section_id);
        }

        return $event->targets->whereNotNull('schedule_id')->contains(
            fn ($target) => (int) $target->schedule?->section_id === (int) $student->section_id
        );
    }

    public function targetsSchedule(SchoolEvent $event, Schedule $schedule): bool
    {
        if ($event->target_scope === 'school') {
            return true;
        }
        $event->loadMissing('targets');

        return $event->target_scope === 'sections'
            ? $event->targets->contains('section_id', $schedule->section_id)
            : $event->targets->contains('schedule_id', $schedule->id);
    }

    public function publishedNear(Carbon $at): Collection
    {
        return SchoolEvent::with('targets.schedule')
            ->where('status', 'published')
            ->where('starts_at', '<=', $at->copy()->addDay())
            ->where('ends_at', '>=', $at->copy()->subDay())
            ->get();
    }
}
