<?php

namespace App\Services;

use App\Models\SchoolEvent;
use App\Models\Student;
use App\ValueObjects\ScheduleOccurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceAbsenceWriter
{
    private const INSERT_CHUNK = 500;

    public function __construct(
        private IntegrityKeyService $keys,
        private AttendanceSummaryCache $cache,
    ) {}

    public function forSchedule(ScheduleOccurrence $occurrence, ?SchoolEvent $event = null): int
    {
        $schedule = $occurrence->schedule;
        $absenceTime = $occurrence->presentUntil->addSecond();
        $attendanceDate = $this->keys->attendanceDate($absenceTime);

        $query = Student::query()
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('attendance_logs as existing', function (JoinClause $join) use ($schedule, $occurrence): void {
                $join->on('existing.user_id', '=', 'students.user_id')
                    ->where('existing.schedule_id', $schedule->id)
                    ->where('existing.attendance_type', 'time_in')
                    ->whereBetween('existing.scan_time', [$occurrence->opensAt, $occurrence->endsAt])
                    ->where(function ($query): void {
                        $query->where('existing.record_state', 'canonical')
                            ->orWhereNull('existing.record_state');
                    });
            })
            ->where('students.section_id', $schedule->section_id)
            ->where('students.status', 'active')
            ->where('users.status', 'active')
            ->whereNull('existing.id')
            ->select(['students.id as student_id', 'students.user_id'])
            ->orderBy('students.id');

        return $this->insertEligible($query, function (int $userId) use ($schedule, $event, $absenceTime, $attendanceDate): array {
            return [
                'user_id' => $userId,
                'schedule_id' => $schedule->id,
                'school_event_id' => $event?->id,
                'attendance_type' => 'time_in',
                'scan_time' => $absenceTime,
                'attendance_date' => $attendanceDate,
                'scan_key' => implode(':', ['student', $userId, $schedule->id, $attendanceDate]),
                'integrity_key' => $this->keys->attendanceKey([
                    'user_id' => $userId,
                    'schedule_id' => $schedule->id,
                    'school_event_id' => $event?->id,
                    'attendance_type' => 'time_in',
                    'attendance_date' => $attendanceDate,
                ]),
                'record_state' => 'canonical',
                'status' => $event ? 'excused' : 'absent',
                'remarks' => $event
                    ? "Class excused due to school event: {$event->title}."
                    : 'Attendance marked as Absent.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });
    }

    public function forEvent(SchoolEvent $event, Builder $students): int
    {
        $absenceTime = $event->ends_at->copy()->addSecond();
        $attendanceDate = $this->keys->attendanceDate($absenceTime);

        $query = $students
            ->join('users', 'users.id', '=', 'students.user_id')
            ->leftJoin('attendance_logs as existing', function (JoinClause $join) use ($event): void {
                $join->on('existing.user_id', '=', 'students.user_id')
                    ->where('existing.school_event_id', $event->id)
                    ->where('existing.attendance_type', 'time_in')
                    ->where(function ($query): void {
                        $query->where('existing.record_state', 'canonical')
                            ->orWhereNull('existing.record_state');
                    });
            })
            ->where('users.status', 'active')
            ->whereNull('existing.id')
            ->select(['students.id as student_id', 'students.user_id'])
            ->orderBy('students.id');

        return $this->insertEligible($query, function (int $userId) use ($event, $absenceTime, $attendanceDate): array {
            return [
                'user_id' => $userId,
                'schedule_id' => null,
                'school_event_id' => $event->id,
                'attendance_type' => 'time_in',
                'scan_time' => $absenceTime,
                'attendance_date' => $attendanceDate,
                'scan_key' => "event:{$userId}:{$event->id}",
                'integrity_key' => $this->keys->attendanceKey([
                    'user_id' => $userId,
                    'school_event_id' => $event->id,
                    'attendance_type' => 'time_in',
                    'attendance_date' => $attendanceDate,
                ]),
                'record_state' => 'canonical',
                'status' => 'absent',
                'remarks' => 'Required school event attendance was missed.',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });
    }

    /**
     * Run one set-based eligibility query and write the resulting rows in
     * bounded batches. There is intentionally no per-student query here.
     */
    private function insertEligible(Builder $query, callable $row): int
    {
        $created = 0;

        $query->chunkById(self::INSERT_CHUNK, function (Collection $students) use (&$created, $row): void {
            $rows = $students->map(fn ($student) => $row((int) $student->user_id))->all();

            if ($rows !== []) {
                $created += DB::table('attendance_logs')->insertOrIgnore($rows);
                $userIds = $students->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
                $this->afterCommit(function () use ($userIds): void {
                    foreach ($userIds as $userId) {
                        $this->cache->invalidateAttendance($userId);
                    }
                });
            }
        }, 'students.id', 'student_id');

        return $created;
    }

    private function afterCommit(\Closure $callback): void
    {
        $callback();

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit($callback);
        }
    }
}
