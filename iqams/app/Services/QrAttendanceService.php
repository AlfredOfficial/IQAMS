<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QrAttendanceService
{
    public function __construct(
        private QrIdentityResolver $identityResolver,
        private StudentAttendanceWindow $studentWindow,
        private AccountStatusService $accountStatus,
    ) {}

    public function record(string $qrCode, ?string $location = null, ?Carbon $scannedAt = null): AttendanceLog
    {
        $scannedAt ??= now();
        $scannedAt = $scannedAt->copy()->timezone(config('app.timezone'));
        $user = $this->identityResolver->resolve(trim($qrCode));

        return DB::transaction(function () use ($user, $location, $scannedAt) {
            $lockedUser = User::with(['role', 'student'])->lockForUpdate()->findOrFail($user->id);

            $this->accountStatus->ensureAccountIsActive($lockedUser, 'qr_code');

            return $lockedUser->student
                ? $this->recordStudent($lockedUser, $scannedAt, $location)
                : $this->recordPersonnel($lockedUser, $scannedAt, $location);
        }, 3);
    }

    private function recordStudent(User $user, Carbon $scannedAt, ?string $location): AttendanceLog
    {
        $student = $user->student;

        if ($student->status !== 'active') {
            $this->deny('Attendance is denied because this student profile is not active.');
        }

        if (! $student->section_id) {
            $this->deny('Attendance is denied because this student has no assigned section.');
        }

        $day = strtolower($scannedAt->format('l'));
        $schedules = Schedule::with(['subject', 'section'])
            ->where('section_id', $student->section_id)
            ->where('day', $day)
            ->get();

        $matches = $schedules
            ->filter(fn (Schedule $schedule) => $this->studentWindow->isOpen($schedule, $scannedAt))
            ->sortByDesc(fn (Schedule $schedule) => [
                $this->studentWindow->isPresent($schedule, $scannedAt) ? 1 : 0,
                $this->studentWindow->start($schedule, $scannedAt)->getTimestamp(),
            ])
            ->values();

        if ($matches->isEmpty()) {
            $nextSchedule = $schedules
                ->filter(fn (Schedule $schedule) => $scannedAt->lessThan($this->studentWindow->opensAt($schedule, $scannedAt)))
                ->sortBy(fn (Schedule $schedule) => $this->studentWindow->opensAt($schedule, $scannedAt)->getTimestamp())
                ->first();

            if ($nextSchedule) {
                $opensAt = $this->studentWindow->opensAt($nextSchedule, $scannedAt)->format('g:i A');
                $this->deny("Attendance scanning opens at {$opensAt}.");
            }

            $this->deny('You do not have a scheduled class at this time.');
        }

        $schedule = $matches->first();
        $scanKey = implode(':', ['student', $user->id, $schedule->id, $scannedAt->toDateString()]);

        $existing = AttendanceLog::where('scan_key', $scanKey)
            ->orWhere(fn ($query) => $query->where('user_id', $user->id)
                ->where('schedule_id', $schedule->id)
                ->whereDate('scan_time', $scannedAt->toDateString())
                ->where('attendance_type', 'time_in'))
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($existing->status === 'absent' && $this->studentWindow->status($schedule, $scannedAt) === 'late') {
                $existing->update([
                    'scan_time' => $scannedAt,
                    'status' => 'late',
                    'scanner_location' => $location,
                    'remarks' => null,
                ]);

                return $existing->load(['user.role', 'schedule.subject', 'schedule.section']);
            }

            $this->deny('Attendance already recorded for this subject.');
        }

        $status = $this->studentWindow->status($schedule, $scannedAt);

        try {
            return AttendanceLog::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'attendance_type' => 'time_in',
                'scan_time' => $scannedAt,
                'scan_key' => $scanKey,
                'status' => $status,
                'scanner_location' => $location,
            ])->load(['user.role', 'schedule.subject', 'schedule.section']);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->deny('Attendance already recorded for this subject.');
            }

            throw $exception;
        }
    }

    private function recordPersonnel(User $user, Carbon $scannedAt, ?string $location): AttendanceLog
    {
        $role = strtolower($user->role?->role_name ?? '');

        if (! in_array($role, ['instructor', 'staff'], true)) {
            $this->deny('This QR code does not belong to a supported attendance profile.');
        }

        $todayLogs = AttendanceLog::where('user_id', $user->id)
            ->whereNull('schedule_id')
            ->whereDate('scan_time', $scannedAt->toDateString())
            ->orderBy('scan_time')
            ->lockForUpdate()
            ->get();

        $latest = $todayLogs->last();
        $cooldown = config('attendance.duplicate_cooldown_seconds');

        $latestLocalTime = $latest
            ? Carbon::parse($latest->getRawOriginal('scan_time'), config('app.timezone'))
            : null;

        if ($latestLocalTime && abs($latestLocalTime->getTimestamp() - $scannedAt->getTimestamp()) < $cooldown) {
            $this->deny("Please wait {$cooldown} seconds before scanning this QR code again.");
        }

        $stages = config("attendance.personnel_windows.{$role}", []);
        $stageNames = array_keys($stages);
        $limit = min(config('attendance.personnel_daily_scan_limit'), count($stageNames));

        if ($todayLogs->count() >= $limit) {
            $this->deny('All required attendance scans for today have already been completed.');
        }

        $period = $stageNames[$todayLogs->count()] ?? null;
        $stage = $period ? $stages[$period] : null;

        if (! $stage || ! $this->isWithinPersonnelWindow($stage, $scannedAt)) {
            if (! $stage) {
                $this->deny('Personnel attendance windows are not configured correctly. Contact the administrator.');
            }

            $startLabel = Carbon::parse($stage['start'])->format('g:i A');
            $endLabel = Carbon::parse($stage['end'])->format('g:i A');
            $this->deny("{$stage['label']} is only allowed from {$startLabel} to {$endLabel}.");
        }

        return AttendanceLog::create([
            'user_id' => $user->id,
            'schedule_id' => null,
            'attendance_type' => $stage['type'],
            'attendance_period' => $period,
            'scan_time' => $scannedAt,
            'status' => $this->punctuality($stage, $scannedAt) === 'late' ? 'late' : 'present',
            'punctuality_status' => $this->punctuality($stage, $scannedAt),
            'scanner_location' => $location,
        ])->load('user.role');
    }

    private function punctuality(array $stage, Carbon $scannedAt): string
    {
        if (($stage['type'] ?? null) === 'time_in' && isset($stage['on_time_until'])) {
            $deadline = $scannedAt->copy()->startOfDay()->setTimeFromTimeString($stage['on_time_until']);

            return $scannedAt->greaterThan($deadline) ? 'late' : 'on_time';
        }

        if (($stage['type'] ?? null) === 'time_out' && isset($stage['not_early_before'])) {
            $minimum = $scannedAt->copy()->startOfDay()->setTimeFromTimeString($stage['not_early_before']);

            return $scannedAt->lessThan($minimum) ? 'early_out' : 'on_time';
        }

        return 'on_time';
    }

    private function isWithinPersonnelWindow(array $stage, Carbon $scannedAt): bool
    {
        $start = $scannedAt->copy()->startOfDay()->setTimeFromTimeString($stage['start']);
        $end = $scannedAt->copy()->startOfDay()->setTimeFromTimeString($stage['end']);

        return $end->greaterThanOrEqualTo($start)
            ? $scannedAt->betweenIncluded($start, $end)
            : $scannedAt->greaterThanOrEqualTo($start) || $scannedAt->lessThanOrEqualTo($end);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['qr_code' => $message]);
    }
}
