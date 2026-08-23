<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\SchoolEvent;
use App\Models\Student;
use Illuminate\Support\Carbon;

class SchoolEventAttendanceService
{
    public function __construct(private SchoolEventResolver $resolver) {}

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
                $this->studentsFor($event)->chunkById(200, function ($students) use ($event, &$created): void {
                    foreach ($students as $student) {
                        $created += AttendanceLog::insertOrIgnore([
                            'user_id' => $student->user_id,
                            'schedule_id' => null,
                            'school_event_id' => $event->id,
                            'attendance_type' => 'time_in',
                            'scan_time' => $event->ends_at->copy()->addSecond(),
                            'scan_key' => "event:{$student->user_id}:{$event->id}",
                            'status' => 'absent',
                            'remarks' => 'Required school event attendance was missed.',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
                $event->update(['attendance_finalized_at' => $at]);
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
