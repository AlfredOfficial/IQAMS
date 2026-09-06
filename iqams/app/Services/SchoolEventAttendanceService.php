<?php

namespace App\Services;

use App\Models\SchoolEvent;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchoolEventAttendanceService
{
    public function __construct(
        private AttendanceAbsenceWriter $writer,
        private StudentScheduleEligibility $eligibility,
    ) {}

    public function markDue(?Carbon $at = null): int
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));
        $created = 0;

        SchoolEvent::with('targets.schedule')
            ->where('status', 'published')
            ->where('attendance_mode', 'event_attendance')
            ->where('ends_at', '<', $at)
            ->whereNull('attendance_finalized_at')
            ->each(function (SchoolEvent $event) use ($at, &$created): void {
                $created += DB::transaction(function () use ($event, $at): int {
                    $locked = SchoolEvent::query()->with('targets.schedule')->lockForUpdate()->find($event->id);

                    if (! $locked || $locked->attendance_finalized_at || $locked->ends_at->gte($at)) {
                        return 0;
                    }

                    $created = $this->writer->forEvent($locked, $this->studentsFor($locked));
                    $locked->update(['attendance_finalized_at' => $at]);

                    return $created;
                });
            });

        return $created;
    }

    public function studentsFor(SchoolEvent $event)
    {
        if ($event->target_scope === 'school') {
            return Student::query()->where('students.status', 'active')
                ->whereHas('user', fn ($q) => $q->where('status', 'active'));
        }

        $sections = $event->target_scope === 'sections'
            ? $event->targets->pluck('section_id')->filter()->map(fn ($id) => (int) $id)->all()
            : $event->targets->pluck('schedule.section_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        $groups = $event->target_scope === 'sections'
            ? \App\Models\Schedule::active()->whereIn('section_id', $sections)->pluck('recurring_schedule_group_id')->all()
            : $event->targets->pluck('schedule.recurring_schedule_group_id')->filter()->unique()->all();

        return $this->eligibility->studentsForTargets($sections, $groups);
    }
}
