<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Resolves the class offerings a student may attend. */
class StudentScheduleEligibility
{
    public function schedulesFor(Student $student): Builder
    {
        $query = Schedule::query()->active();

        if ($student->enrollment_type === 'irregular') {
            return $query->whereIn('recurring_schedule_group_id', $student->scheduleEnrollments()
                ->select('recurring_schedule_group_id'));
        }

        return $query->where('section_id', $student->section_id);
    }

    public function isEligibleForSchedule(Student $student, Schedule $schedule): bool
    {
        if ($student->enrollment_type !== 'irregular') {
            return (int) $student->section_id === (int) $schedule->section_id;
        }

        return $this->schedulesFor($student)->whereKey($schedule->id)->exists();
    }

    public function isEligibleForSection(Student $student, int $sectionId): bool
    {
        if ($student->enrollment_type !== 'irregular') {
            return (int) $student->section_id === $sectionId;
        }

        return $this->schedulesFor($student)->where('section_id', $sectionId)->exists();
    }

    /** Active students eligible for a schedule, without duplicate rows. */
    public function studentsForSchedule(Schedule $schedule): Builder
    {
        return Student::query()
            ->where('students.status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->where(function (Builder $query) use ($schedule): void {
                $query->where(function (Builder $regular) use ($schedule): void {
                    $regular->where('students.enrollment_type', 'regular')
                        ->where('students.section_id', $schedule->section_id);
                })->orWhere(function (Builder $irregular) use ($schedule): void {
                    $irregular->where('students.enrollment_type', 'irregular')
                        ->whereHas('scheduleEnrollments', fn (Builder $enrollments) => $enrollments
                            ->where('recurring_schedule_group_id', $schedule->recurring_schedule_group_id));
                });
            });
    }

    /** @param array<int, int> $sectionIds @param array<int, string> $groupIds */
    public function studentsForTargets(array $sectionIds, array $groupIds): Builder
    {
        return Student::query()
            ->where('students.status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('status', 'active'))
            ->where(function (Builder $query) use ($sectionIds, $groupIds): void {
                $query->where(function (Builder $regular) use ($sectionIds): void {
                    $regular->where('students.enrollment_type', 'regular')->whereIn('students.section_id', $sectionIds);
                })->orWhere(function (Builder $irregular) use ($sectionIds, $groupIds): void {
                    $irregular->where('students.enrollment_type', 'irregular')
                        ->whereHas('scheduleEnrollments', fn (Builder $enrollments) => $enrollments->whereIn('recurring_schedule_group_id', $groupIds));
                });
            });
    }

    /** @param array<int, string> $groupIds */
    public function sync(Student $student, array $groupIds): void
    {
        $student->scheduleEnrollments()->delete();

        if ($student->enrollment_type === 'irregular') {
            $student->scheduleEnrollments()->createMany(
                collect($groupIds)->unique()->map(fn (string $id) => ['recurring_schedule_group_id' => $id])->all(),
            );
        }

        app(AttendanceSummaryCache::class)->invalidateStudentContext();
    }

    /** @return Collection<int, string> */
    public function groupIdsFor(Student $student): Collection
    {
        return $student->scheduleEnrollments()->pluck('recurring_schedule_group_id');
    }
}
