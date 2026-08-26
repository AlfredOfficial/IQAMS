<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstructorClassAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recurring_schedule_rows_are_grouped_without_losing_day_schedule_ids(): void
    {
        [$instructorUser, , $subject, $section] = $this->classroom();
        $schedules = collect(['monday', 'wednesday', 'friday'])->map(fn (string $day) => $this->schedule(
            $instructorUser->instructor, $subject, $section, $day,
        ));

        $response = $this->actingAs($instructorUser)->get(route('instructor.schedule'));

        $response->assertOk()->assertViewHas('scheduleGroups', function ($groups) use ($schedules) {
            $group = $groups->sole();

            return $group['days_label'] === 'MWF'
                && collect($group['days'])->pluck('schedule_id')->all() === $schedules->pluck('id')->all();
        });
    }

    public function test_attendance_is_filtered_by_schedule_section_and_actual_date(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');
        [$instructorUser, $otherInstructorUser, $subject, $section, $otherSection] = $this->classroom();
        $monday = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');
        $wednesday = $this->schedule($instructorUser->instructor, $subject, $section, 'wednesday');
        $otherSchedule = $this->schedule($otherInstructorUser->instructor, $subject, $otherSection, 'monday');

        $present = $this->student($section, 'STU-001', 'Ana', 'Present');
        $late = $this->student($section, 'STU-002', 'Ben', 'Late');
        $absent = $this->student($section, 'STU-003', 'Cara', 'Absent');
        $excused = $this->student($section, 'STU-004', 'Dan', 'Excused');
        $missing = $this->student($section, 'STU-005', 'Ella', 'Missing');
        $outsider = $this->student($otherSection, 'STU-999', 'Other', 'Section');

        $this->log($present, $monday, '2026-08-24 10:02:00', 'present');
        $this->log($late, $monday, '2026-08-24 10:20:00', 'late');
        $this->log($absent, $monday, '2026-08-24 10:16:00', 'absent');
        $this->log($excused, $monday, '2026-08-24 10:16:00', 'excused');
        $this->log($present, $wednesday, '2026-08-26 10:03:00', 'present');
        $this->log($outsider, $otherSchedule, '2026-08-24 10:01:00', 'present');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $monday).'?date=2026-08-24')
            ->assertOk()
            ->assertJsonPath('class.date', '2026-08-24')
            ->assertJsonPath('summary.present', 2)
            ->assertJsonPath('summary.absent', 2)
            ->assertJsonPath('summary.excused', 1)
            ->assertJsonPath('summary.pending', 0)
            ->assertJsonCount(5, 'students')
            ->assertJsonMissing(['student_no' => 'STU-999'])
            ->assertJsonFragment(['student_no' => $missing->student_no, 'status' => 'absent', 'recorded' => false]);
    }

    public function test_missing_scans_remain_pending_before_the_class_cutoff(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        [$instructorUser, , $subject, $section] = $this->classroom();
        $wednesday = $this->schedule($instructorUser->instructor, $subject, $section, 'wednesday');
        $student = $this->student($section, 'STU-010', 'Future', 'Student');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $wednesday).'?date=2026-08-26')
            ->assertOk()
            ->assertJsonPath('has_records', false)
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonFragment(['student_no' => $student->student_no, 'status' => 'pending']);
    }

    public function test_instructor_cannot_view_another_instructors_schedule(): void
    {
        [$instructorUser, $otherInstructorUser, $subject, , $otherSection] = $this->classroom();
        $schedule = $this->schedule($otherInstructorUser->instructor, $subject, $otherSection, 'monday');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $schedule).'?date=2026-08-24')
            ->assertForbidden();
    }

    public function test_date_must_match_the_selected_schedule_weekday(): void
    {
        [$instructorUser, , $subject, $section] = $this->classroom();
        $monday = $this->schedule($instructorUser->instructor, $subject, $section, 'monday');

        $this->actingAs($instructorUser)
            ->getJson(route('instructor.schedule.attendance', $monday).'?date=2026-08-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    private function classroom(): array
    {
        $instructorRole = Role::firstOrCreate(['role_name' => 'instructor']);
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-3A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $otherSection = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-3B', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'IT301', 'subject_name' => 'Systems Analysis', 'units' => 3]);

        $users = collect(['One', 'Two'])->map(function (string $lastName) use ($instructorRole, $department) {
            $user = User::factory()->create(['role_id' => $instructorRole->id, 'status' => 'active']);
            Instructor::create(['user_id' => $user->id, 'department_id' => $department->id, 'employee_no' => 'INS-'.$lastName, 'first_name' => 'Instructor', 'last_name' => $lastName]);

            return $user->load('instructor');
        });

        return [$users[0], $users[1], $subject, $section, $otherSection];
    }

    private function schedule(Instructor $instructor, Subject $subject, Section $section, string $day): Schedule
    {
        return Schedule::create(['subject_id' => $subject->id, 'instructor_id' => $instructor->id, 'section_id' => $section->id, 'day' => $day, 'start_time' => '10:00', 'end_time' => '11:00', 'room' => '204']);
    }

    private function student(Section $section, string $number, string $firstName, string $lastName): Student
    {
        $user = User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => 'student'])->id, 'status' => 'active']);

        return Student::create(['user_id' => $user->id, 'student_no' => $number, 'first_name' => $firstName, 'last_name' => $lastName, 'section_id' => $section->id, 'course_id' => $section->course_id, 'status' => 'active']);
    }

    private function log(Student $student, Schedule $schedule, string $time, string $status): void
    {
        AttendanceLog::create(['user_id' => $student->user_id, 'schedule_id' => $schedule->id, 'attendance_type' => 'time_in', 'scan_time' => $time, 'status' => $status]);
    }
}
