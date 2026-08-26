<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffAttendancePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_attendance_pages_use_staff_layout_and_staff_navigation(): void
    {
        Carbon::setTestNow('2026-08-26 10:00:00');
        $staff = $this->user('staff');

        foreach ([
            'staff.attendance.history' => 'staff.attendance.history',
            'staff.attendance.summary' => 'staff.attendance.summary',
            'staff.attendance.issues' => 'staff.attendance.issues',
        ] as $route => $view) {
            $this->actingAs($staff)->get(route($route))
                ->assertOk()
                ->assertViewIs($view)
                ->assertSee('Staff Portal')
                ->assertSee(route('staff.dashboard'), false)
                ->assertSee(route('staff.leave-requests.index'), false)
                ->assertSee(route('staff.profile.edit'), false)
                ->assertDontSee('Instructor Portal');
        }
    }

    public function test_staff_history_only_contains_the_authenticated_staff_records(): void
    {
        Carbon::setTestNow('2026-08-26 10:00:00');
        $staff = $this->user('staff');
        $other = $this->user('staff');
        $this->log($staff, '2026-08-25 08:11:00');
        $this->log($other, '2026-08-25 09:47:00');

        $this->actingAs($staff)->get(route('staff.attendance.history', [
            'from' => '2026-08-25', 'to' => '2026-08-25',
        ]))->assertOk()
            ->assertSee('8:11 AM')
            ->assertDontSee('9:47 AM');
    }

    public function test_staff_summary_and_issues_use_personnel_attendance_data(): void
    {
        Carbon::setTestNow('2026-08-26 10:00:00');
        $staff = $this->user('staff');
        $this->log($staff, '2026-08-25 08:11:00', 'morning_in', 'late');

        $this->actingAs($staff)->get(route('staff.attendance.summary', [
            'month' => 8, 'year' => 2026,
        ]))->assertOk()->assertSee('Monthly Attendance Summary')->assertSee('8:11 AM');

        $this->actingAs($staff)->get(route('staff.attendance.issues'))
            ->assertOk()->assertSee('Incomplete')->assertSee('August 25, 2026');
    }

    public function test_non_staff_users_cannot_access_staff_attendance_pages(): void
    {
        $instructor = $this->user('instructor');

        foreach (['history', 'summary', 'issues'] as $page) {
            $this->actingAs($instructor)
                ->get(route("staff.attendance.{$page}"))
                ->assertForbidden();
        }
    }

    public function test_existing_instructor_attendance_pages_still_use_instructor_views(): void
    {
        Carbon::setTestNow('2026-08-26 10:00:00');
        $instructor = $this->user('instructor');

        foreach (['history', 'summary', 'issues'] as $page) {
            $this->actingAs($instructor)
                ->get(route("instructor.{$page}"))
                ->assertOk()
                ->assertViewIs("instructor.{$page}")
                ->assertSee('Instructor Portal')
                ->assertDontSee('Staff Portal');
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
        ]);
    }

    private function log(User $user, string $time, string $period = 'morning_in', string $punctuality = 'on_time'): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'attendance_period' => $period,
            'scan_time' => $time,
            'status' => 'present',
            'punctuality_status' => $punctuality,
        ]);
    }
}
