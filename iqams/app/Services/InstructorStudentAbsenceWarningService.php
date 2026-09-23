<?php

namespace App\Services;

use App\Models\Instructor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstructorStudentAbsenceWarningService
{
    /** @return Collection<int, array<string, mixed>> */
    public function forInstructor(Instructor $instructor): Collection
    {
        $rows = DB::table('attendance_logs')
            ->join('schedules', 'schedules.id', '=', 'attendance_logs.schedule_id')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->join('sections', 'sections.id', '=', 'schedules.section_id')
            ->join('students', 'students.user_id', '=', 'attendance_logs.user_id')
            ->leftJoin('school_events', 'school_events.id', '=', 'attendance_logs.school_event_id')
            ->where('schedules.instructor_id', $instructor->id)
            ->where('attendance_logs.attendance_type', 'time_in')
            ->where('attendance_logs.status', 'absent')
            ->where(function ($query): void {
                $query->where('attendance_logs.record_state', 'canonical')
                    ->orWhereNull('attendance_logs.record_state');
            })
            ->where(function ($query): void {
                $query->whereNull('school_events.attendance_mode')
                    ->orWhere('school_events.attendance_mode', '<>', 'cancelled');
            })
            ->orderBy('students.last_name')->orderBy('students.first_name')
            ->orderBy('subjects.subject_code')->orderBy('attendance_logs.attendance_date')
            ->get([
                'students.id as student_id',
                'students.student_no',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'subjects.id as subject_id',
                'subjects.subject_code',
                'subjects.subject_name',
                'sections.id as section_id',
                'sections.section_name',
                'attendance_logs.attendance_date',
            ]);

        return $rows->groupBy(fn ($row) => implode('|', [$row->student_id, $row->subject_id, $row->section_id]))
            ->map(function (Collection $group): array {
                $first = $group->first();
                return [
                    'student_no' => $first->student_no,
                    'student_name' => trim(implode(' ', array_filter([$first->first_name, $first->middle_name, $first->last_name]))),
                    'subject_code' => $first->subject_code,
                    'subject_name' => $first->subject_name,
                    'section_name' => $first->section_name,
                    'absence_count' => $group->count(),
                    'absence_dates' => $group->pluck('attendance_date')->map(fn ($date) => (string) $date)->values(),
                    'status' => 'At Risk',
                ];
            })
            ->filter(fn (array $warning) => $warning['absence_count'] >= StudentAbsenceWarningService::THRESHOLD)
            ->sortByDesc('absence_count')->values();
    }

}
