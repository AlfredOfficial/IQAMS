<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentAbsenceWarningService
{
    public const THRESHOLD = 5;

    /**
     * Return every subject in the student's current section that has reached
     * the absence warning threshold.
     */
    public function forStudent(Student $student): Collection
    {
        if (! $student->section_id) {
            return collect();
        }

        return AttendanceLog::query()
            ->join('schedules', 'attendance_logs.schedule_id', '=', 'schedules.id')
            ->join('subjects', 'schedules.subject_id', '=', 'subjects.id')
            ->where('attendance_logs.user_id', $student->user_id)
            ->where('schedules.section_id', $student->section_id)
            ->where('attendance_logs.attendance_type', 'time_in')
            ->where('attendance_logs.status', 'absent')
            ->groupBy('subjects.id', 'subjects.subject_code', 'subjects.subject_name')
            ->havingRaw('COUNT(attendance_logs.id) >= ?', [self::THRESHOLD])
            ->orderByDesc('absence_count')
            ->orderBy('subjects.subject_code')
            ->get([
                'subjects.id as subject_id',
                'subjects.subject_code',
                'subjects.subject_name',
                DB::raw('COUNT(attendance_logs.id) as absence_count'),
            ]);
    }
}
