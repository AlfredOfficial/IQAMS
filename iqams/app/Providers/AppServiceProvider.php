<?php

namespace App\Providers;

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
use App\Observers\AttendanceSummaryCacheObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $observer = AttendanceSummaryCacheObserver::class;
        AttendanceLog::observe($observer);
        Course::observe($observer);
        Department::observe($observer);
        Instructor::observe($observer);
        LeaveRequest::observe($observer);
        NonTeachingStaff::observe($observer);
        Schedule::observe($observer);
        SchoolEvent::observe($observer);
        SchoolEventTarget::observe($observer);
        Section::observe($observer);
        Student::observe($observer);
        Subject::observe($observer);
        User::observe($observer);

        // The fixed admin portal role is the production super-admin role.
        Gate::before(function (User $user, string $ability) {
            // The audit trail is deliberately permission-gated even for the
            // admin super-role so access can be revoked independently.
            if ($ability === 'view-audit-logs') {
                return null;
            }

            return $user->hasRole('admin') ? true : null;
        });
    }
}
