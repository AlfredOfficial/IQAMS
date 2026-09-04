<?php

namespace App\Observers;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\NonTeachingStaff;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\SchoolEventTarget;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttendanceSummaryCache;
use App\Services\DashboardReferenceCache;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AttendanceSummaryCacheObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $cache = app(AttendanceSummaryCache::class);

        $this->afterCommit(function () use ($cache, $model): void {
            if ($model instanceof AttendanceLog) {
                $cache->invalidateAttendance((int) $model->user_id);

                return;
            }

            if ($model instanceof LeaveRequest) {
                $cache->invalidateLeave((int) $model->user_id);

                return;
            }

            if ($model instanceof Student) {
                $cache->forgetStudent((int) $model->user_id);
                $cache->forgetAdminAnalytics();

                return;
            }

            if ($model instanceof User) {
                $cache->invalidateAll();

                return;
            }

            if ($model instanceof Instructor || $model instanceof NonTeachingStaff) {
                $cache->invalidatePersonnelContext();

                return;
            }

            if ($model instanceof Schedule || $model instanceof SchoolEvent || $model instanceof SchoolEventTarget
                || $model instanceof Section || $model instanceof Subject || $model instanceof Department
                || $model instanceof Course) {
                if ($model instanceof Course) {
                    DashboardReferenceCache::forget();
                }

                $cache->invalidateStudentContext();
                $cache->invalidatePersonnelContext();
            }
        });
    }

    private function afterCommit(Closure $callback): void
    {
        // Invalidate immediately so a summary read inside the same database
        // transaction cannot reuse a stale value, then repeat after commit so
        // a concurrent reader cannot repopulate a value before the write is
        // durable.
        $callback();

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit($callback);
        }
    }
}
