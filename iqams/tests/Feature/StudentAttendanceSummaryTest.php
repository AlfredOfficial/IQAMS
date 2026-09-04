<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentAttendanceSummary;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_present_and_late_are_attended_while_absent_is_rated_and_excused_is_excluded(): void
    {
        [$student, $schedule] = $this->fixture();
        $dates = [
            Carbon::parse('2026-08-03 08:05', 'Asia/Manila'),
            Carbon::parse('2026-08-10 08:20', 'Asia/Manila'),
            Carbon::parse('2026-08-17 09:00', 'Asia/Manila'),
        ];

        $this->log($student->user, $schedule, $dates[0], 'present');
        $this->log($student->user, $schedule, $dates[1], 'late');
        $this->log($student->user, $schedule, $dates[2], 'absent');

        $event = SchoolEvent::create([
            'title' => 'Cancelled Class',
            'starts_at' => Carbon::parse('2026-08-24 08:00', 'Asia/Manila'),
            'ends_at' => Carbon::parse('2026-08-24 10:00', 'Asia/Manila'),
            'attendance_mode' => 'cancelled',
            'target_scope' => 'schedules',
            'status' => 'published',
            'published_at' => Carbon::parse('2026-08-20 08:00', 'Asia/Manila'),
        ]);
        AttendanceLog::create([
            'user_id' => $student->user_id,
            'school_event_id' => $event->id,
            'attendance_type' => 'time_in',
            'scan_time' => Carbon::parse('2026-08-24 10:01', 'Asia/Manila'),
            'status' => 'excused',
        ]);

        $summary = app(StudentAttendanceSummary::class)->forStudent($student->fresh());

        $this->assertSame(1, $summary['present']);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(1, $summary['excused']);
        $this->assertSame(2, $summary['attended']);
        $this->assertSame(3, $summary['scheduled']);
        $this->assertSame(1, $summary['excluded']);
        $this->assertSame(66.67, $summary['percentage']);
    }

    public function test_archived_schedule_records_are_not_counted_as_scheduled_attendance(): void
    {
        [$student, $schedule] = $this->fixture();
        $schedule->update(['archived_at' => now()]);
        $this->log($student->user, $schedule, Carbon::parse('2026-08-03 08:00', 'Asia/Manila'), 'present');

        $summary = app(StudentAttendanceSummary::class)->forStudent($student->fresh());

        $this->assertSame(0, $summary['scheduled']);
        $this->assertSame(0.0, $summary['percentage']);
    }

    public function test_cached_summary_is_invalidated_when_attendance_is_created(): void
    {
        [$student, $schedule] = $this->fixture();
        $service = app(StudentAttendanceSummary::class);

        $this->assertSame(0, $service->forStudent($student->fresh())['present']);

        $log = $this->log($student->user, $schedule, Carbon::parse('2026-08-03 08:05', 'Asia/Manila'), 'present');

        $this->assertSame(1, $service->forStudent($student->fresh())['present']);

        $log->update(['status' => 'late']);
        $this->assertSame(1, $service->forStudent($student->fresh())['late']);

        $log->update(['record_state' => 'voided']);
        $this->assertSame(0, $service->forStudent($student->fresh())['scheduled']);
    }

    /** @return array{0: Student, 1: Schedule} */
    private function fixture(): array
    {
        $department = Department::create(['department_code' => 'SUM', 'department_name' => 'Summary Department']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'SUM101', 'course_name' => 'Summary Course']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'SUM-A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'SUM101', 'subject_name' => 'Summary Subject', 'units' => 3]);
        $instructorUser = User::factory()->create(['role_id' => Role::findByName('instructor', 'web')->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'SUM-INS',
            'first_name' => 'Summary',
            'last_name' => 'Instructor',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Room 1',
        ]);
        $studentUser = User::factory()->create(['role_id' => Role::findByName('student', 'web')->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_no' => 'SUM-STU',
            'first_name' => 'Summary',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        return [$student, $schedule];
    }

    private function log(User $user, Schedule $schedule, Carbon $at, string $status): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'attendance_type' => 'time_in',
            'scan_time' => $at,
            'status' => $status,
        ]);
    }
}
