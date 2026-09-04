<?php

namespace App\Services;

use App\Exceptions\AttendanceAlreadyRecordedException;
use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\User;
use App\Support\LocalTimeRange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QrAttendanceService
{
    public function __construct(
        private QrIdentityResolver $identityResolver,
        private StudentAttendanceWindow $studentWindow,
        private ScheduleOccurrenceResolver $occurrences,
        private AccountStatusService $accountStatus,
        private ApprovedLeaveAttendanceGuard $leaveGuard,
        private SchoolEventResolver $eventResolver,
        private PersonnelAttendanceClassifier $personnelClassifier,
    ) {}

    public function record(string $qrCode, ?string $location = null, ?Carbon $scannedAt = null): AttendanceLog
    {
        $scannedAt ??= now();
        $scannedAt = $scannedAt->copy()->timezone(config('app.timezone'));
        $user = $this->identityResolver->resolve(trim($qrCode));

        return DB::transaction(function () use ($user, $location, $scannedAt) {
            $lockedUser = User::with(['roles', 'student'])->lockForUpdate()->findOrFail($user->id);

            $this->accountStatus->ensureAccountIsActive($lockedUser, 'qr_code');
            $this->leaveGuard->ensureAttendanceIsAllowed($lockedUser, $scannedAt, 'qr_code');

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

        $candidateDates = $this->occurrences->candidateSessionDates($scannedAt);
        $candidateDays = collect($candidateDates)
            ->map(fn (Carbon $date) => strtolower($date->format('l')))
            ->all();
        $schedules = Schedule::active()->with(['subject', 'section'])
            ->where('section_id', $student->section_id)
            ->whereIn('day', $candidateDays)
            ->get();

        $candidates = $schedules
            ->map(fn (Schedule $schedule) => [
                'schedule' => $schedule,
                'occurrence' => $this->occurrences->resolveAt($schedule, $scannedAt),
            ])
            ->filter(fn (array $candidate) => $candidate['occurrence'] !== null);
        $eventContext = $this->eventContext($scannedAt, $candidates);

        if ($event = $this->eventResolver->activeAttendanceEvent($student, $scannedAt, $eventContext)) {
            return $this->recordEvent($user, $event, $scannedAt, $location);
        }

        $matches = $candidates
            ->reject(fn (array $candidate) => $this->eventResolver->affectingOccurrence($candidate['occurrence'], $eventContext))
            ->sortByDesc(fn (array $candidate) => [
                $this->studentWindow->isPresent($candidate['occurrence'], $scannedAt) ? 1 : 0,
                $candidate['occurrence']->startsAt->getTimestamp(),
            ])
            ->values();

        if ($matches->isEmpty()) {
            $nextSchedule = $schedules
                ->map(fn (Schedule $schedule) => [
                    'schedule' => $schedule,
                    'occurrence' => $this->occurrences->forDate($schedule, $scannedAt),
                ])
                ->filter(fn (array $candidate) => $candidate['occurrence']
                    && $scannedAt->lessThan($candidate['occurrence']->opensAt))
                ->sortBy(fn (array $candidate) => $candidate['occurrence']->opensAt->getTimestamp())
                ->first();

            if ($nextSchedule) {
                $opensAt = $nextSchedule['occurrence']->opensAt->format('g:i A');
                $this->deny("Attendance scanning opens at {$opensAt}.");
            }

            $this->deny('You do not have a scheduled class at this time.');
        }

        $schedule = $matches->first()['schedule'];
        $occurrence = $matches->first()['occurrence'];
        $scanKey = implode(':', ['student', $user->id, $schedule->id, $scannedAt->toDateString()]);

        $existing = AttendanceLog::canonical()->where(function ($query) use ($scanKey, $user, $schedule, $occurrence) {
            $query->where('scan_key', $scanKey)
                ->orWhere(fn ($query) => $query->where('user_id', $user->id)
                ->where('schedule_id', $schedule->id)
                ->whereBetween('scan_time', [$occurrence->opensAt, $occurrence->endsAt])
                ->where('attendance_type', 'time_in'));
        })
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($existing->status === 'absent' && $this->studentWindow->status($occurrence, $scannedAt) === 'late') {
                $existing->update([
                    'scan_time' => $scannedAt,
                    'scan_key' => $scanKey,
                    'status' => 'late',
                    'scanner_location' => $location,
                    'remarks' => null,
                ]);

                return $existing->load(['user.roles', 'schedule.subject', 'schedule.section']);
            }

            throw AttendanceAlreadyRecordedException::forLog(
                $existing->load(['user.roles', 'schedule.subject', 'schedule.section']),
                'Attendance already recorded for this subject.',
            );
        }

        $status = $this->studentWindow->status($occurrence, $scannedAt);

        try {
            return AttendanceLog::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'attendance_type' => 'time_in',
                'scan_time' => $scannedAt,
                'scan_key' => $scanKey,
                'status' => $status,
                'scanner_location' => $location,
            ])->load(['user.roles', 'schedule.subject', 'schedule.section']);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $existing = AttendanceLog::canonical()->where('scan_key', $scanKey)->first();

                if ($existing) {
                    throw AttendanceAlreadyRecordedException::forLog(
                        $existing->load(['user.roles', 'schedule.subject', 'schedule.section']),
                        'Attendance already recorded for this subject.',
                    );
                }

                $this->deny('Attendance already recorded for this subject.');
            }

            throw $exception;
        }
    }

    /**
     * Build one event collection for the scan and all resolved candidate
     * occurrences. The resolver expands the range by its existing matching
     * window before querying published events.
     */
    private function eventContext(Carbon $scannedAt, Collection $candidates): SchoolEventContext
    {
        $from = $scannedAt->copy();
        $to = $scannedAt->copy();

        foreach ($candidates as $candidate) {
            $occurrence = $candidate['occurrence'];
            $from = $occurrence->startsAt->lt($from) ? $occurrence->startsAt->copy() : $from;
            $to = $occurrence->endsAt->gt($to) ? $occurrence->endsAt->copy() : $to;
        }

        return $this->eventResolver->context($from, $to);
    }

    private function recordEvent(User $user, SchoolEvent $event, Carbon $scannedAt, ?string $location): AttendanceLog
    {
        $scanKey = "event:{$user->id}:{$event->id}";
        $existing = AttendanceLog::canonical()->where('scan_key', $scanKey)->lockForUpdate()->first();
        $presentUntil = $event->starts_at->copy()->addMinutes(config('attendance.present_grace_minutes'))->endOfMinute();
        $status = $scannedAt->lessThanOrEqualTo($presentUntil) ? 'present' : 'late';

        if ($existing) {
            if ($existing->status === 'absent') {
                $existing->update(['scan_time' => $scannedAt, 'status' => $status, 'scanner_location' => $location, 'remarks' => null]);

                return $existing->load(['user.roles', 'schoolEvent']);
            }
            throw AttendanceAlreadyRecordedException::forLog(
                $existing->load(['user.roles', 'schoolEvent']),
                'Attendance already recorded for this school event.',
            );
        }

        return AttendanceLog::create([
            'user_id' => $user->id,
            'schedule_id' => null,
            'school_event_id' => $event->id,
            'attendance_type' => 'time_in',
            'scan_time' => $scannedAt,
            'scan_key' => $scanKey,
            'status' => $status,
            'scanner_location' => $location,
        ])->load(['user.roles', 'schoolEvent']);
    }

    private function recordPersonnel(User $user, Carbon $scannedAt, ?string $location): AttendanceLog
    {
        $role = strtolower((string) $user->primaryRoleName());

        if (! in_array($role, ['instructor', 'staff'], true)) {
            $this->deny('This QR code does not belong to a supported attendance profile.');
        }

        [$dayStart, $dayEnd] = LocalTimeRange::day($scannedAt);
        $todayLogs = AttendanceLog::canonical()->where('user_id', $user->id)
            ->whereNull('schedule_id')
            ->where('scan_time', '>=', $dayStart)
            ->where('scan_time', '<', $dayEnd)
            ->orderBy('scan_time')
            ->lockForUpdate()
            ->get();

        $latest = $todayLogs->last();
        $cooldown = config('attendance.duplicate_cooldown_seconds');

        $latestLocalTime = $latest
            ? Carbon::parse($latest->getRawOriginal('scan_time'), config('app.timezone'))
            : null;

        if ($latestLocalTime && abs($latestLocalTime->getTimestamp() - $scannedAt->getTimestamp()) < $cooldown) {
            throw AttendanceAlreadyRecordedException::forLog(
                $latest->load('user.roles'),
                "Please wait {$cooldown} seconds before scanning this QR code again.",
            );
        }

        $stages = config("attendance.personnel_windows.{$role}", []);
        $stageNames = array_keys($stages);
        $limit = min(config('attendance.personnel_daily_scan_limit'), count($stageNames));

        if ($todayLogs->count() >= $limit) {
            throw AttendanceAlreadyRecordedException::forLog(
                $latest->load('user.roles'),
                'All required attendance scans for today have already been completed.',
            );
        }

        if ($role === 'instructor') {
            // Instructor scans represent the period whose window is open now. Walking
            // the stages in reverse makes a shared boundary (for example 12:30 PM)
            // belong to the later period.
            $period = collect($stages)
                ->reverse()
                ->search(fn (array $candidate) => $this->personnelClassifier->isWithinWindow($candidate, $scannedAt));
            $period = $period === false ? null : $period;
            $stage = $period ? $stages[$period] : null;

            if ($period && $todayLogs->contains('attendance_period', $period)) {
                $existing = $todayLogs->firstWhere('attendance_period', $period);
                throw AttendanceAlreadyRecordedException::forLog(
                    $existing->load('user.roles'),
                    "{$stage['label']} has already been recorded.",
                );
            }
        } else {
            $period = $stageNames[$todayLogs->count()] ?? null;
            $stage = $period ? $stages[$period] : null;
        }

        if (! $stage || ! $this->personnelClassifier->isWithinWindow($stage, $scannedAt)) {
            if (! $stage) {
                if ($role === 'instructor') {
                    $upcomingStage = collect($stages)->first(function (array $candidate) use ($scannedAt) {
                        $startsAt = $scannedAt->copy()->startOfDay()->setTimeFromTimeString($candidate['start']);

                        return $scannedAt->lessThan($startsAt);
                    });

                    if ($upcomingStage) {
                        $startLabel = Carbon::parse($upcomingStage['start'])->format('g:i A');
                        $endLabel = Carbon::parse($upcomingStage['end'])->format('g:i A');
                        $this->deny("{$upcomingStage['label']} is only allowed from {$startLabel} to {$endLabel}.");
                    }

                    $this->deny('Instructor attendance cannot be recorded because no attendance window is open at this time.');
                }

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
            'status' => $this->personnelClassifier->punctuality($stage, $scannedAt) === 'late' ? 'late' : 'present',
            'punctuality_status' => $this->personnelClassifier->punctuality($stage, $scannedAt),
            'scanner_location' => $location,
        ])->load('user.roles');
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
