<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AttendanceSummaryCache
{
    private const STUDENT_GENERATION = 'attendance-summary.student-generation.v1';

    private const PERSONNEL_GENERATION = 'attendance-summary.personnel-generation.v1';

    private const STUDENT_TTL = 60;

    private const PERSONNEL_TTL = 60;

    private const ADMIN_ANALYTICS_TTL = 15;

    public function rememberStudent(int $userId, Closure $callback): array
    {
        return Cache::remember(
            $this->studentKey($userId),
            now()->addSeconds(self::STUDENT_TTL),
            $callback,
        );
    }

    public function rememberPersonnel(int $userId, Carbon $from, Carbon $to, bool $includeEmpty, Closure $callback): mixed
    {
        return Cache::remember(
            $this->personnelKey($userId, $from, $to, $includeEmpty),
            now()->addSeconds(self::PERSONNEL_TTL),
            $callback,
        );
    }

    public function rememberAdminAnalytics(Closure $callback): array
    {
        return Cache::remember(
            'admin-dashboard.analytics.v2',
            now()->addSeconds(self::ADMIN_ANALYTICS_TTL),
            $callback,
        );
    }

    public function forgetStudent(int $userId): void
    {
        Cache::forget($this->studentKey($userId));
    }

    public function forgetPersonnel(int $userId, ?Carbon $from = null, ?Carbon $to = null, ?bool $includeEmpty = null): void
    {
        if ($from && $to && $includeEmpty !== null) {
            Cache::forget($this->personnelKey($userId, $from, $to, $includeEmpty));
        }

        // A user can have several requested ranges cached. Bumping the
        // user's generation invalidates all of them without affecting other
        // personnel dashboards.
        $this->bump($this->personnelUserGenerationKey($userId));
    }

    public function forgetAdminAnalytics(): void
    {
        Cache::forget('admin-dashboard.analytics.v2');
    }

    public function invalidateAttendance(int $userId): void
    {
        $this->forgetStudent($userId);
        $this->forgetPersonnel($userId);
        $this->forgetAdminAnalytics();
    }

    public function invalidateLeave(int $userId): void
    {
        $this->forgetPersonnel($userId);
        $this->forgetAdminAnalytics();
    }

    public function invalidateStudentContext(): void
    {
        $this->bump(self::STUDENT_GENERATION);
        $this->forgetAdminAnalytics();
    }

    public function invalidatePersonnelContext(): void
    {
        $this->bump(self::PERSONNEL_GENERATION);
        $this->forgetAdminAnalytics();
    }

    public function invalidateAll(): void
    {
        $this->bump(self::STUDENT_GENERATION);
        $this->bump(self::PERSONNEL_GENERATION);
        $this->forgetAdminAnalytics();
    }

    private function studentKey(int $userId): string
    {
        return 'attendance-summary.student.v2:'.$this->generation(self::STUDENT_GENERATION).':'.$userId;
    }

    private function personnelKey(int $userId, Carbon $from, Carbon $to, bool $includeEmpty): string
    {
        return implode(':', [
            'attendance-summary.personnel.v2',
            $this->generation(self::PERSONNEL_GENERATION),
            $this->generation($this->personnelUserGenerationKey($userId)),
            $userId,
            $from->toDateString(),
            $to->toDateString(),
            $includeEmpty ? '1' : '0',
        ]);
    }

    private function generation(string $key): int
    {
        Cache::add($key, 1, now()->addYear());

        return (int) Cache::get($key, 1);
    }

    private function bump(string $key): void
    {
        Cache::add($key, 1, now()->addYear());
        Cache::increment($key);
    }

    private function personnelUserGenerationKey(int $userId): string
    {
        return 'attendance-summary.personnel-user-generation.v1:'.$userId;
    }
}
