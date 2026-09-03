<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Student;

class StudentAttendanceSummary
{
    /**
     * Build the student's attendance totals from canonical time-in records.
     * Excused and cancelled sessions are excluded from the denominator.
     *
     * @return array{present:int,late:int,absent:int,excused:int,attended:int,excluded:int,scheduled:int,percentage:float}
     */
    public function forStudent(Student $student): array
    {
        $logs = AttendanceLog::canonical()
            ->where('user_id', $student->user_id)
            ->where('attendance_type', 'time_in')
            ->where(function ($query): void {
                $query->whereNotNull('schedule_id')->orWhereNotNull('school_event_id');
            })
            ->with(['schedule', 'schoolEvent'])
            ->get()
            ->filter(fn (AttendanceLog $log): bool => $this->belongsToStudentSession($log, $student));

        $cancelled = $logs->filter(fn (AttendanceLog $log): bool => $log->schoolEvent?->attendance_mode === 'cancelled');
        $excluded = $logs->filter(fn (AttendanceLog $log): bool => $log->status === 'excused' || $cancelled->contains('id', $log->id));
        $rated = $logs->reject(fn (AttendanceLog $log): bool => $excluded->contains('id', $log->id));

        $present = $rated->where('status', 'present')->count();
        $late = $rated->where('status', 'late')->count();
        $absent = $rated->where('status', 'absent')->count();
        $attended = $present + $late;
        $scheduled = $attended + $absent;

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $logs->where('status', 'excused')->count(),
            'attended' => $attended,
            'excluded' => $excluded->count(),
            'scheduled' => $scheduled,
            'percentage' => $scheduled > 0 ? round(($attended / $scheduled) * 100, 2) : 0.0,
        ];
    }

    private function belongsToStudentSession(AttendanceLog $log, Student $student): bool
    {
        if ($log->schoolEvent) {
            return true;
        }

        return $log->schedule
            && (int) $log->schedule->section_id === (int) $student->section_id
            && $log->schedule->archived_at === null;
    }
}
