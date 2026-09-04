<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\PersonnelAttendancePages;
use App\Services\PersonnelAttendanceSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PersonnelAttendanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_calendar_distinguishes_attendance_absence_leave_and_exclusions(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        $staff = $this->staff();
        $this->log($staff, '2026-08-03 08:15:00', 'late');
        LeaveRequest::create([
            'user_id' => $staff->id, 'leave_type' => 'vacation',
            'start_date' => '2026-08-05', 'end_date' => '2026-08-05',
            'reason' => 'Approved personal leave.', 'status' => 'approved',
        ]);
        $this->nonWorkingDay('Leave-day holiday', '2026-08-05');
        $this->nonWorkingDay('Holiday', '2026-08-06');
        $this->nonWorkingDay('Work suspension', '2026-08-07');
        Carbon::setTestNow('2026-08-10 09:00:00');

        $days = app(PersonnelAttendanceSummary::class)->days(
            $staff, Carbon::parse('2026-07-31'), Carbon::parse('2026-08-08'), true,
        );
        $byDate = $days->keyBy(fn ($day) => $day['date']->toDateString());
        $totals = app(PersonnelAttendanceSummary::class)->totals($days);

        $this->assertSame('Present', $byDate['2026-08-03']['status']);
        $this->assertTrue($byDate['2026-08-03']['late']);
        $this->assertSame('Absent', $byDate['2026-08-04']['status']);
        $this->assertSame('On Leave', $byDate['2026-08-05']['status']);
        $this->assertSame('Excluded', $byDate['2026-08-06']['status']);
        $this->assertStringContainsString('Holiday', $byDate['2026-08-06']['exclusionReason']);
        $this->assertSame('Excluded', $byDate['2026-08-07']['status']);
        $this->assertSame('Weekend', $byDate['2026-08-08']['exclusionReason']);
        $this->assertSame('Before employment start', $byDate['2026-07-31']['exclusionReason']);
        $this->assertSame(1, $totals['absentDays']);
        $this->assertSame(1, $totals['leaveDays']);
        $this->assertSame(1, $totals['presentDays']);
        $this->assertSame(3, $totals['expectedDays']);
        $this->assertSame(6, $totals['excludedDays']);

        $issues = app(PersonnelAttendancePages::class)->issues($staff)['days'];
        $this->assertTrue($issues->contains(fn ($day) => $day['date']->isSameDay('2026-08-04')));
        $this->assertFalse($issues->contains(fn ($day) => $day['isExcluded'] || $day['leave']));
    }

    public function test_inactive_employee_without_attendance_is_excluded(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        $staff = $this->staff('inactive');
        Carbon::setTestNow('2026-08-04 18:00:00');

        $day = app(PersonnelAttendanceSummary::class)->days(
            $staff, Carbon::parse('2026-08-03'), Carbon::parse('2026-08-03'), true,
        )->first();

        $this->assertSame('Excluded', $day['status']);
        $this->assertSame('Employee inactive', $day['exclusionReason']);
    }

    public function test_daily_progress_uses_only_recorded_attendance_periods(): void
    {
        $service = app(PersonnelAttendanceSummary::class);

        foreach (range(0, 4) as $completed) {
            $logs = collect(PersonnelAttendanceSummary::PERIODS)
                ->take($completed)
                ->values()
                ->map(fn ($period, $index) => new AttendanceLog([
                    'attendance_period' => $period,
                    'scan_time' => today()->copy()->addHours(8 + $index),
                ]));
            $day = $service->day(today(), $logs);

            $this->assertSame($completed, $day['completedPeriods']);
            $this->assertSame($completed * 25, $day['progressPercentage']);
        }
    }

    public function test_monthly_totals_require_all_four_periods_for_a_present_day(): void
    {
        Carbon::setTestNow('2026-08-10 14:00:00');
        $service = app(PersonnelAttendanceSummary::class);

        foreach (range(1, 3) as $completed) {
            $today = $service->day(today(), $this->periodLogs(today(), $completed));
            $totals = $service->totals(collect([$today]));

            $this->assertSame($completed * 25, $today['progressPercentage']);
            $this->assertSame('In Progress', $today['status']);
            $this->assertSame('Present', $today['summaryStatus']);
            $this->assertSame(0, $totals['presentDays']);
            $this->assertSame(1, $totals['inProgressCount']);
            $this->assertSame(0, $totals['percentage']);
        }

        $today = $service->day(today(), $this->periodLogs(today(), 4));
        $totals = $service->totals(collect([$today]));

        $this->assertSame(100, $today['progressPercentage']);
        $this->assertSame('Present', $today['status']);
        $this->assertSame(1, $totals['presentDays']);
        $this->assertSame(100.0, $totals['percentage']);
    }

    public function test_previous_partial_zero_and_complete_workdays_are_classified_by_day(): void
    {
        Carbon::setTestNow('2026-08-10 14:00:00');
        $service = app(PersonnelAttendanceSummary::class);
        $partial = $service->day(Carbon::parse('2026-08-07'), $this->periodLogs(Carbon::parse('2026-08-07'), 1));
        $absent = $service->day(Carbon::parse('2026-08-06'), collect());
        $present = $service->day(Carbon::parse('2026-08-05'), $this->periodLogs(Carbon::parse('2026-08-05'), 4));
        $totals = $service->totals(collect([$partial, $absent, $present]));

        $this->assertSame('Incomplete', $partial['status']);
        $this->assertSame('Absent', $absent['status']);
        $this->assertSame('Present', $present['status']);
        $this->assertSame(1, $totals['incompleteCount']);
        $this->assertSame(1, $totals['absentDays']);
        $this->assertSame(1, $totals['presentDays']);
        $this->assertSame(50.0, $totals['percentage']);
    }

    public function test_attendance_rate_excludes_current_in_progress_and_incomplete_days(): void
    {
        Carbon::setTestNow('2026-08-10 14:00:00');
        $service = app(PersonnelAttendanceSummary::class);
        $days = collect();
        foreach (range(1, 15) as $offset) {
            $date = today()->copy()->subDays($offset + 2);
            $days->push($service->day($date, $this->periodLogs($date, 4)));
        }
        $days->push($service->day(today()->copy()->subDay(), collect()));
        $days->push($service->day(today()->copy()->subDays(2), collect()));
        $days->push($service->day(today(), $this->periodLogs(today(), 1)));

        $totals = $service->totals($days);

        $this->assertSame(15, $totals['presentDays']);
        $this->assertSame(2, $totals['absentDays']);
        $this->assertSame(1, $totals['inProgressCount']);
        $this->assertSame(88.24, $totals['percentage']);
    }

    public function test_instructor_expected_days_follow_existing_teaching_schedule(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        [$user, $instructor] = $this->instructor();
        $this->schedule($instructor, 'monday');
        Carbon::setTestNow('2026-08-05 18:00:00');

        $days = app(PersonnelAttendanceSummary::class)->days(
            $user, Carbon::parse('2026-08-03'), Carbon::parse('2026-08-04'), true,
        )->keyBy(fn ($day) => $day['date']->toDateString());

        $this->assertSame('Absent', $days['2026-08-03']['status']);
        $this->assertSame('Not scheduled', $days['2026-08-04']['exclusionReason']);
    }

    public function test_instructor_with_no_schedule_has_no_absence(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        [$user] = $this->instructor();
        Carbon::setTestNow('2026-08-04 18:00:00');

        $days = app(PersonnelAttendanceSummary::class)->days(
            $user, Carbon::parse('2026-08-03'), Carbon::parse('2026-08-03'), true,
        );

        $this->assertSame('Not scheduled', $days->first()['exclusionReason']);
        $this->assertSame(0, app(PersonnelAttendanceSummary::class)->totals($days)['absentDays']);
    }

    public function test_cached_calendar_is_invalidated_when_attendance_is_created(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        $staff = $this->staff();
        Carbon::setTestNow('2026-08-10 09:00:00');
        $service = app(PersonnelAttendanceSummary::class);
        $from = Carbon::parse('2026-08-03');
        $to = Carbon::parse('2026-08-03');

        $this->assertSame('Absent', $service->days($staff, $from, $to, true)->first()['status']);

        $this->log($staff, '2026-08-03 08:15:00', 'on_time');

        $this->assertSame('Present', $service->days($staff, $from, $to, true)->first()['status']);
    }

    public function test_cached_calendar_is_invalidated_when_leave_is_approved(): void
    {
        Carbon::setTestNow('2026-08-01 09:00:00');
        $staff = $this->staff();
        Carbon::setTestNow('2026-08-10 09:00:00');
        $service = app(PersonnelAttendanceSummary::class);
        $from = Carbon::parse('2026-08-03');
        $to = Carbon::parse('2026-08-03');

        $this->assertSame('Absent', $service->days($staff, $from, $to, true)->first()['status']);

        LeaveRequest::create([
            'user_id' => $staff->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'reason' => 'Approved leave.',
            'status' => 'approved',
        ]);

        $this->assertSame('On Leave', $service->days($staff, $from, $to, true)->first()['status']);
    }

    private function staff(string $status = 'active'): User
    {
        $user = $this->user('staff', $status);
        NonTeachingStaff::create([
            'user_id' => $user->id, 'employee_no' => 'STF'.$user->id,
            'first_name' => 'Staff', 'last_name' => (string) $user->id,
        ]);

        return $user;
    }

    private function periodLogs(Carbon $date, int $completed): Collection
    {
        return collect(self::periodDefinitions())
            ->take($completed)
            ->map(fn (array $period) => new AttendanceLog([
                'attendance_period' => $period[0],
                'scan_time' => $date->copy()->setTime($period[1], 0),
                'punctuality_status' => 'on_time',
            ]));
    }

    private static function periodDefinitions(): array
    {
        return [
            ['morning_in', 8],
            ['lunch_out', 12],
            ['afternoon_in', 13],
            ['final_out', 17],
        ];
    }

    private function instructor(): array
    {
        $department = Department::create([
            'department_code' => 'D'.uniqid(), 'department_name' => 'Test Department',
        ]);
        $user = $this->user('instructor');
        $instructor = Instructor::create([
            'user_id' => $user->id, 'department_id' => $department->id,
            'employee_no' => 'INS'.$user->id, 'first_name' => 'Instructor', 'last_name' => (string) $user->id,
        ]);

        return [$user, $instructor, $department];
    }

    private function schedule(Instructor $instructor, string $day): Schedule
    {
        $department = $instructor->department;
        $course = Course::create([
            'department_id' => $department->id, 'course_code' => 'C'.uniqid(), 'course_name' => 'Test Course',
        ]);
        $section = Section::create([
            'course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st',
        ]);
        $subject = Subject::create([
            'subject_code' => 'S'.uniqid(), 'subject_name' => 'Test Subject', 'units' => 3,
        ]);

        return Schedule::create([
            'subject_id' => $subject->id, 'instructor_id' => $instructor->id,
            'section_id' => $section->id, 'day' => $day,
            'start_time' => '08:00', 'end_time' => '10:00', 'room' => '101',
        ]);
    }

    private function user(string $role, string $status = 'active'): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
            'status' => $status,
        ]);
    }

    private function log(User $user, string $date, string $punctuality): void
    {
        foreach ([
            ['morning_in', 'time_in', $date],
            ['lunch_out', 'time_out', '2026-08-03 12:00:00'],
            ['afternoon_in', 'time_in', '2026-08-03 13:00:00'],
            ['final_out', 'time_out', '2026-08-03 17:00:00'],
        ] as [$period, $type, $time]) {
            AttendanceLog::create([
                'user_id' => $user->id, 'attendance_type' => $type,
                'attendance_period' => $period, 'scan_time' => $time,
                'status' => 'present', 'punctuality_status' => $period === 'morning_in' ? $punctuality : 'on_time',
            ]);
        }
    }

    private function nonWorkingDay(string $title, string $date): SchoolEvent
    {
        return SchoolEvent::create([
            'title' => $title, 'starts_at' => $date.' 00:00:00', 'ends_at' => $date.' 23:59:59',
            'attendance_mode' => 'cancelled', 'target_scope' => 'school',
            'status' => 'published', 'published_at' => $date.' 00:00:00',
        ]);
    }
}
