<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdminDashboardData;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_counts_student_and_personnel_absences_after_cutoff(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $this->studentWithSchedule();
        $staffUser = User::factory()->create(['role_id' => Role::findByName('staff', 'web')->id]);
        NonTeachingStaff::create([
            'user_id' => $staffUser->id,
            'office_unit_id' => OfficeUnit::create(['code' => 'ABS', 'name' => 'Absence Office', 'is_active' => true])->id,
            'employee_no' => 'ABS-STAFF',
            'first_name' => 'Absent',
            'last_name' => 'Staff',
        ]);

        $stats = app(AdminDashboardData::class)->build()['stats'];

        $this->assertSame(2, $stats['absent']);
    }

    public function test_personnel_not_yet_past_first_cutoff_are_not_counted_absent(): void
    {
        Carbon::setTestNow('2026-08-19 07:00:00');
        $staffUser = User::factory()->create(['role_id' => Role::findByName('staff', 'web')->id]);
        NonTeachingStaff::create([
            'user_id' => $staffUser->id,
            'office_unit_id' => OfficeUnit::create(['code' => 'WAIT', 'name' => 'Waiting Office', 'is_active' => true])->id,
            'employee_no' => 'WAIT-STAFF',
            'first_name' => 'Waiting',
            'last_name' => 'Staff',
        ]);

        $this->assertSame(0, app(AdminDashboardData::class)->build()['stats']['absent']);
    }

    public function test_student_absence_records_before_the_class_cutoff_are_not_counted_absent(): void
    {
        Carbon::setTestNow('2026-08-19 08:10:00');
        $student = $this->studentWithSchedule();
        $schedule = Schedule::where('section_id', $student->section_id)->firstOrFail();
        AttendanceLog::where('user_id', $student->user_id)
            ->where('schedule_id', $schedule->id)
            ->firstOrFail()
            ->update(['scan_time' => now()->setTime(8, 9)]);

        $this->assertSame(0, app(AdminDashboardData::class)->build()['stats']['absent']);
    }

    private function studentWithSchedule(): Student
    {
        $department = Department::create(['department_code' => 'ABS', 'department_name' => 'Absence Department']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'ABS101', 'course_name' => 'Absence Course']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'ABS-A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'ABS101', 'subject_name' => 'Absence Subject', 'units' => 3]);
        $instructorUser = User::factory()->create(['role_id' => Role::findByName('instructor', 'web')->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'ABS-INS',
            'first_name' => 'Present',
            'last_name' => 'Instructor',
        ]);
        AttendanceLog::create([
            'user_id' => $instructorUser->id,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'scan_time' => now()->setTime(7, 30),
            'status' => 'present',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'wednesday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Room 1',
        ]);
        $studentUser = User::factory()->create(['role_id' => Role::findByName('student', 'web')->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_no' => 'ABS-STU',
            'first_name' => 'Absent',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
        AttendanceLog::create([
            'user_id' => $studentUser->id,
            'schedule_id' => $schedule->id,
            'attendance_type' => 'time_in',
            'scan_time' => now()->setTime(8, 16),
            'status' => 'absent',
        ]);

        return $student;
    }
}
