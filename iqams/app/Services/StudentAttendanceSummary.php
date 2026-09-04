<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentAttendanceSummary
{
    public function __construct(private AttendanceSummaryCache $cache) {}

    /**
     * Build the student's attendance totals from canonical time-in records.
     * Excused and cancelled sessions are excluded from the denominator.
     *
     * @return array{present:int,late:int,absent:int,excused:int,attended:int,excluded:int,scheduled:int,percentage:float}
     */
    public function forStudent(Student $student): array
    {
        return $this->cache->rememberStudent((int) $student->user_id, function () use ($student): array {
            $cancelled = "COALESCE(school_events.attendance_mode, '') = 'cancelled'";
            $notExcluded = "attendance_logs.status <> 'excused' AND NOT ({$cancelled})";

            $query = DB::table('attendance_logs')
                ->leftJoin('schedules', 'schedules.id', '=', 'attendance_logs.schedule_id')
                ->leftJoin('school_events', 'school_events.id', '=', 'attendance_logs.school_event_id')
                ->where(function ($query): void {
                    $query->where('attendance_logs.record_state', 'canonical')
                        ->orWhereNull('attendance_logs.record_state');
                })
                ->where('attendance_logs.user_id', $student->user_id)
                ->where('attendance_logs.attendance_type', 'time_in')
                ->where(function ($query): void {
                    $query->whereNotNull('attendance_logs.schedule_id')
                        ->orWhereNotNull('attendance_logs.school_event_id');
                })
                ->where(function ($query) use ($student): void {
                    $query->where(function ($query) use ($student): void {
                        $query->whereNotNull('schedules.id')
                            ->where('schedules.section_id', $student->section_id)
                            ->whereNull('schedules.archived_at');
                    })->orWhereNotNull('school_events.id');
                })
                ->selectRaw("\n                    COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'present' THEN 1 ELSE 0 END), 0) AS present,\n                    COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'late' THEN 1 ELSE 0 END), 0) AS late,\n                    COALESCE(SUM(CASE WHEN {$notExcluded} AND attendance_logs.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent,\n                    COALESCE(SUM(CASE WHEN attendance_logs.status = 'excused' THEN 1 ELSE 0 END), 0) AS excused,\n                    COALESCE(SUM(CASE WHEN {$cancelled} OR attendance_logs.status = 'excused' THEN 1 ELSE 0 END), 0) AS excluded\n                ")
                ->first();

            $present = (int) ($query->present ?? 0);
            $late = (int) ($query->late ?? 0);
            $absent = (int) ($query->absent ?? 0);
            $excused = (int) ($query->excused ?? 0);
            $excluded = (int) ($query->excluded ?? 0);
            $attended = $present + $late;
            $scheduled = $attended + $absent;

            return [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'attended' => $attended,
                'excluded' => $excluded,
                'scheduled' => $scheduled,
                'percentage' => $scheduled > 0 ? round(($attended / $scheduled) * 100, 2) : 0.0,
            ];
        });
    }
}
