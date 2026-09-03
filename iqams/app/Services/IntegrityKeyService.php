<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Section;
use Illuminate\Support\Carbon;

class IntegrityKeyService
{
    public function attendanceDate(mixed $scanTime): ?string
    {
        if (! $scanTime) {
            return null;
        }

        return Carbon::parse($scanTime)->timezone(config('app.timezone'))->toDateString();
    }

    public function attendanceKey(array $attributes): ?string
    {
        $date = $attributes['attendance_date'] ?? $this->attendanceDate($attributes['scan_time'] ?? null);
        $userId = $attributes['user_id'] ?? null;
        $type = $attributes['attendance_type'] ?? null;

        if (! $userId || ! $type) {
            return null;
        }

        if (! empty($attributes['school_event_id'])) {
            $identity = implode('|', ['event', $userId, $attributes['school_event_id'], $type]);
        } elseif (! empty($attributes['schedule_id'])) {
            if (! $date) {
                return null;
            }
            $identity = implode('|', ['schedule', $userId, $attributes['schedule_id'], $type, $date]);
        } elseif (! empty($attributes['attendance_period'])) {
            if (! $date) {
                return null;
            }
            $identity = implode('|', ['personnel', $userId, $attributes['attendance_period'], $date]);
        } else {
            return null;
        }

        return hash('sha256', $identity);
    }

    public function forAttendance(AttendanceLog $log): ?string
    {
        return $this->attendanceKey($log->getAttributes());
    }

    public function sectionKey(Section $section): string
    {
        return hash('sha256', implode('|', [
            $section->course_id,
            $this->normalize($section->section_name),
            $this->normalize($section->school_year),
            $this->normalize($section->semester),
        ]));
    }

    public function sectionIsActive(Section $section): bool
    {
        return ! $section->archived_at
            && $section->course()->whereNull('archived_at')->exists();
    }

    public function scheduleKey(Schedule $schedule): string
    {
        return hash('sha256', implode('|', [
            $schedule->subject_id,
            $schedule->instructor_id,
            $schedule->section_id,
            $this->normalize($schedule->day),
            substr((string) $schedule->start_time, 0, 8),
            substr((string) $schedule->end_time, 0, 8),
            $this->normalize($schedule->room),
        ]));
    }

    public function scheduleIsActive(Schedule $schedule): bool
    {
        return ! $schedule->archived_at
            && $schedule->subject()->whereNull('archived_at')->exists()
            && $schedule->section()
                ->whereNull('archived_at')
                ->whereHas('course', fn ($course) => $course->whereNull('archived_at'))
                ->exists();
    }

    private function normalize(?string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $value))) ?? '';
    }
}
