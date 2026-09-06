<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentAbsenceWarningService
{
    public const THRESHOLD = 5;

    public function __construct(private StudentScheduleEligibility $eligibility) {}

    /**
     * Return every subject in the student's current section that has reached
     * the absence warning threshold.
     */
    public function forStudent(Student $student): Collection
    {
        return AttendanceLog::canonical()
            ->join('schedules', 'attendance_logs.schedule_id', '=', 'schedules.id')
            ->join('subjects', 'schedules.subject_id', '=', 'subjects.id')
            ->where('attendance_logs.user_id', $student->user_id)
            ->whereIn('schedules.id', $this->eligibility->schedulesFor($student)->select('schedules.id'))
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
