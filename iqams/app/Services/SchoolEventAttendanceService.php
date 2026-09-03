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
        $query = Student::query()->where('students.status', 'active')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'));

        if ($event->target_scope === 'sections') {
            $query->whereIn('section_id', $event->targets->pluck('section_id')->filter());
        } elseif ($event->target_scope === 'schedules') {
            $query->whereIn('section_id', $event->targets->pluck('schedule.section_id')->filter()->unique());
        }

        return $query;
    }
}
