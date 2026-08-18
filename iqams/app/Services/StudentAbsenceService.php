<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Support\Carbon;

class StudentAbsenceService
{
    public function __construct(private StudentAttendanceWindow $window) {}

    public function markDue(?Carbon $at = null): int
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));
        $day = strtolower($at->format('l'));
        $created = 0;

        $schedules = Schedule::where('day', $day)->get()
            ->filter(fn (Schedule $schedule) => $at->greaterThan($this->window->presentUntil($schedule, $at)));

        foreach ($schedules as $schedule) {
            $absenceTime = $this->window->presentUntil($schedule, $at)->addSecond();

            Student::query()
                ->where('section_id', $schedule->section_id)
                ->where('status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->select(['id', 'user_id'])
                ->chunkById(200, function ($students) use ($schedule, $absenceTime, &$created): void {
                    foreach ($students as $student) {
                        $scanKey = implode(':', [
                            'student',
                            $student->user_id,
                            $schedule->id,
                            $absenceTime->toDateString(),
                        ]);

                        $alreadyRecorded = AttendanceLog::where('user_id', $student->user_id)
                            ->where('schedule_id', $schedule->id)
                            ->whereDate('scan_time', $absenceTime->toDateString())
                            ->where('attendance_type', 'time_in')
                            ->exists();

                        if ($alreadyRecorded) {
                            continue;
                        }

                        $created += AttendanceLog::insertOrIgnore([
                            'user_id' => $student->user_id,
                            'schedule_id' => $schedule->id,
                            'attendance_type' => 'time_in',
                            'scan_time' => $absenceTime,
                            'scan_key' => $scanKey,
                            'status' => 'absent',
                            'remarks' => 'Attendance marked as Absent.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        return $created;
    }
}
