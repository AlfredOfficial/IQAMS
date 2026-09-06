<?php

namespace Tests\Feature;

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
use App\Services\AttendanceScheduleValidator;
use App\Services\SchoolEventResolver;
use App\Services\StudentScheduleEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IrregularStudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_irregular_student_is_eligible_only_for_selected_cross_section_offerings(): void
    {
        [$student, $selected, $unselected] = $this->context();
        $eligibility = app(StudentScheduleEligibility::class);

        $this->assertSame([$selected->id], $eligibility->schedulesFor($student)->pluck('id')->all());
        $this->assertTrue($eligibility->isEligibleForSchedule($student, $selected));
        $this->assertFalse($eligibility->isEligibleForSchedule($student, $unselected));

        app(AttendanceScheduleValidator::class)->validate(
            $student->user, $selected, Carbon::parse('2026-09-07 08:00:00', config('app.timezone')),
        );

        $this->expectException(ValidationException::class);
        app(AttendanceScheduleValidator::class)->validate(
            $student->user, $unselected, Carbon::parse('2026-09-07 08:00:00', config('app.timezone')),
        );
    }

    public function test_section_event_includes_irregular_student_only_for_an_enrolled_offering(): void
    {
        [$student, $selected, $unselected] = $this->context();
        $event = SchoolEvent::create([
            'title' => 'Selected section event', 'starts_at' => '2026-09-07 08:00:00', 'ends_at' => '2026-09-07 09:00:00',
            'attendance_mode' => 'event_attendance', 'target_scope' => 'sections', 'status' => 'published', 'published_at' => now(),
        ]);
        $event->targets()->create(['section_id' => $selected->section_id]);

        $this->assertTrue(app(SchoolEventResolver::class)->targetsStudent($event, $student));

        $event->targets()->delete();
        $event->targets()->create(['section_id' => $unselected->section_id]);
        $event->load('targets.schedule');

        $this->assertFalse(app(SchoolEventResolver::class)->targetsStudent($event, $student));
    }

    private function context(): array
    {
        $studentRole = Role::firstOrCreate(['role_name' => 'student']);
        $department = Department::create(['department_code' => 'IRR', 'department_name' => 'Irregular Department']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'IT']);
        $home = Section::create(['course_id' => $course->id, 'section_name' => 'Home', 'school_year' => '2026-2027', 'semester' => '1st']);
        $selectedSection = Section::create(['course_id' => $course->id, 'section_name' => 'Selected', 'school_year' => '2026-2027', 'semester' => '1st']);
        $otherSection = Section::create(['course_id' => $course->id, 'section_name' => 'Other', 'school_year' => '2026-2027', 'semester' => '1st']);
        $instructorUser = User::factory()->create();
        $instructor = Instructor::create(['user_id' => $instructorUser->id, 'department_id' => $department->id, 'employee_no' => 'IRR-001', 'first_name' => 'Ira', 'last_name' => 'Teacher']);
        $subject = Subject::create(['subject_code' => 'IRR101', 'subject_name' => 'Irregular Class', 'units' => 3]);
        $selected = Schedule::create(['subject_id' => $subject->id, 'instructor_id' => $instructor->id, 'section_id' => $selectedSection->id, 'recurring_schedule_group_id' => (string) Str::uuid(), 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => '101']);
        $unselected = Schedule::create(['subject_id' => $subject->id, 'instructor_id' => $instructor->id, 'section_id' => $otherSection->id, 'recurring_schedule_group_id' => (string) Str::uuid(), 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => '102']);
        $user = User::factory()->create(['role_id' => $studentRole->id]);
        $student = Student::create(['user_id' => $user->id, 'student_no' => 'IRR-001', 'first_name' => 'Irregular', 'last_name' => 'Student', 'course_id' => $course->id, 'section_id' => $home->id, 'enrollment_type' => 'irregular', 'status' => 'active']);
        $student->scheduleEnrollments()->create(['recurring_schedule_group_id' => $selected->recurring_schedule_group_id]);

        return [$student->load('user'), $selected, $unselected];
    }
}
