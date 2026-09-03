<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IntegrityReportService
{
    public function __construct(
        private IntegrityKeyService $keys,
        private LeaveOverlapService $leaveOverlaps,
    ) {}

    public function report(): array
    {
        return [
            'attendance_duplicates' => $this->duplicateAttendanceGroups()->map(fn (Collection $group) => [
                'ids' => $group->pluck('id')->all(),
                'canonical_id' => $this->canonicalAttendance($group)->getKey(),
            ])->values()->all(),
            'leave_overlaps' => $this->leaveOverlaps->groups()->map(fn (Collection $group) => [
                'ids' => $group->pluck('id')->all(),
                'user_id' => $group->first()->user_id,
            ])->values()->all(),
            'schedule_duplicates' => $this->duplicateScheduleGroups()->map(fn (Collection $group) => [
                'ids' => $group->pluck('id')->all(),
                'canonical_id' => $group->sortBy('id')->first()->getKey(),
            ])->values()->all(),
            'section_duplicates' => $this->duplicateSectionGroups()->map(fn (Collection $group) => [
                'ids' => $group->pluck('id')->all(),
                'canonical_id' => $group->sortBy('id')->first()->getKey(),
            ])->values()->all(),
            'invalid_schedule_times' => Schedule::query()
                ->whereColumn('start_time', 'end_time')
                ->pluck('id')->values()->all(),
            'student_course_section_mismatches' => Student::query()
                ->join('sections', 'sections.id', '=', 'students.section_id')
                ->whereColumn('sections.course_id', '!=', 'students.course_id')
                ->pluck('students.id')->values()->all(),
            'orphaned_attendance_schedules' => AttendanceLog::query()
                ->whereNotNull('schedule_id')
                ->whereDoesntHave('schedule')
                ->pluck('id')->values()->all(),
            'orphaned_attendance_users' => AttendanceLog::query()
                ->whereDoesntHave('user')
                ->pluck('id')->values()->all(),
            'orphaned_leave_users' => DB::table('leave_requests')
                ->leftJoin('users', 'users.id', '=', 'leave_requests.user_id')
                ->whereNull('users.id')
                ->pluck('leave_requests.id')->values()->all(),
            'orphaned_attendance_events' => AttendanceLog::query()
                ->whereNotNull('school_event_id')
                ->whereDoesntHave('schoolEvent')
                ->pluck('id')->values()->all(),
            'orphaned_student_courses' => Student::query()
                ->whereDoesntHave('course')
                ->pluck('id')->values()->all(),
            'orphaned_student_users' => Student::query()
                ->whereDoesntHave('user')
                ->pluck('id')->values()->all(),
            'orphaned_student_sections' => Student::query()
                ->whereNotNull('section_id')
                ->whereDoesntHave('section')
                ->pluck('id')->values()->all(),
            'orphaned_courses' => DB::table('courses')
                ->leftJoin('departments', 'departments.id', '=', 'courses.department_id')
                ->whereNull('departments.id')
                ->pluck('courses.id')->values()->all(),
            'orphaned_sections' => DB::table('sections')
                ->leftJoin('courses', 'courses.id', '=', 'sections.course_id')
                ->whereNull('courses.id')
                ->pluck('sections.id')->values()->all(),
            'orphaned_instructor_users' => DB::table('instructors')
                ->leftJoin('users', 'users.id', '=', 'instructors.user_id')
                ->whereNull('users.id')
                ->pluck('instructors.id')->values()->all(),
            'orphaned_instructor_departments' => DB::table('instructors')
                ->leftJoin('departments', 'departments.id', '=', 'instructors.department_id')
                ->whereNull('departments.id')
                ->pluck('instructors.id')->values()->all(),
            'orphaned_staff_users' => DB::table('non_teaching_staff')
                ->leftJoin('users', 'users.id', '=', 'non_teaching_staff.user_id')
                ->whereNull('users.id')
                ->pluck('non_teaching_staff.id')->values()->all(),
            'orphaned_schedules' => Schedule::query()
                ->where(fn ($query) => $query
                    ->whereDoesntHave('subject')
                    ->orWhereDoesntHave('instructor')
                    ->orWhereDoesntHave('section'))
                ->pluck('id')->values()->all(),
            'orphaned_event_targets' => DB::table('school_event_targets as targets')
                ->leftJoin('school_events', 'school_events.id', '=', 'targets.school_event_id')
                ->leftJoin('sections', 'sections.id', '=', 'targets.section_id')
                ->leftJoin('schedules', 'schedules.id', '=', 'targets.schedule_id')
                ->where(fn ($query) => $query
                    ->whereNull('school_events.id')
                    ->orWhere(fn ($query) => $query->whereNotNull('targets.section_id')->whereNull('sections.id'))
                    ->orWhere(fn ($query) => $query->whereNotNull('targets.schedule_id')->whereNull('schedules.id')))
                ->pluck('targets.id')->values()->all(),
            'orphaned_qr_credentials' => DB::table('qr_credentials')
                ->leftJoin('users', 'users.id', '=', 'qr_credentials.user_id')
                ->whereNull('users.id')
                ->pluck('qr_credentials.id')->values()->all(),
        ];
    }

    public function duplicateAttendanceGroups(): Collection
    {
        $grouped = collect();

        AttendanceLog::canonical()->orderBy('id')->chunkById(500, function (Collection $logs) use (&$grouped): void {
            foreach ($logs as $log) {
                $key = $this->keys->forAttendance($log);
                if ($key) {
                    $grouped->push([$key, $log]);
                }
            }
        });

        return $grouped->groupBy(fn (array $item) => $item[0])
            ->map(fn (Collection $items) => $items->pluck(1))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    public function canonicalAttendance(Collection $group): AttendanceLog
    {
        return $group->sort(function (AttendanceLog $left, AttendanceLog $right): int {
            $updated = $right->updated_at?->getTimestamp() <=> $left->updated_at?->getTimestamp();
            if ($updated !== 0) {
                return $updated;
            }

            $scanned = $right->scan_time?->getTimestamp() <=> $left->scan_time?->getTimestamp();
            if ($scanned !== 0) {
                return $scanned;
            }

            return $left->getKey() <=> $right->getKey();
        })->firstOrFail();
    }

    public function duplicateScheduleGroups(): Collection
    {
        return Schedule::active()->get()
            ->groupBy(fn (Schedule $schedule) => $this->keys->scheduleKey($schedule))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    public function duplicateSectionGroups(): Collection
    {
        return Section::active()->get()
            ->groupBy(fn (Section $section) => $this->keys->sectionKey($section))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    public function counts(): array
    {
        $report = $this->report();

        return collect($report)->mapWithKeys(fn (array $items, string $name) => [$name => count($items)])->all();
    }
}
