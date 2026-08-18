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
use App\Services\StudentAbsenceWarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentAbsenceWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_warning_appears_on_student_pages_at_five_absences_and_remains_after_five(): void
    {
        [$user, $student, $schedule] = $this->studentWithSchedule();

        $this->createLogs($user, $schedule, 4, 'absent');
        $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertDontSee('Attendance warning');

        $fifth = $this->createLog($user, $schedule, 5, 'absent');
        $this->get(route('student.dashboard'))->assertOk()
            ->assertSee('Attendance warning')->assertSee('CAP2')->assertSee('5 absences');
        $this->get(route('student.attendance'))->assertOk()
            ->assertSee('Attendance warning')->assertSee('CAP2')->assertSee('5 absences');

        $this->createLog($user, $schedule, 6, 'absent');
        $this->get(route('student.dashboard'))->assertSee('6 absences');

        $fifth->update(['status' => 'present']);
        $this->assertSame(5, app(StudentAbsenceWarningService::class)->forStudent($student)->first()->absence_count);
    }

    public function test_warning_combines_schedules_by_subject_and_lists_every_affected_subject(): void
    {
        [$user, $student, $schedule] = $this->studentWithSchedule();
        $secondSchedule = Schedule::create([
            'subject_id' => $schedule->subject_id, 'instructor_id' => $schedule->instructor_id,
            'section_id' => $schedule->section_id, 'day' => 'wednesday',
            'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'Lab 1',
        ]);
        $otherSubject = Subject::create(['subject_code' => 'ENG1', 'subject_name' => 'English 1', 'units' => 3]);
        $otherSchedule = Schedule::create([
            'subject_id' => $otherSubject->id, 'instructor_id' => $schedule->instructor_id,
            'section_id' => $schedule->section_id, 'day' => 'friday',
            'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Room 2',
        ]);

        $this->createLogs($user, $schedule, 3, 'absent');
        $this->createLogs($user, $secondSchedule, 2, 'absent', 10);
        $this->createLogs($user, $otherSchedule, 5, 'absent', 20);

        $warnings = app(StudentAbsenceWarningService::class)->forStudent($student);

        $this->assertCount(2, $warnings);
        $this->assertSame(5, (int) $warnings->firstWhere('subject_code', 'CAP2')->absence_count);
        $this->assertSame(5, (int) $warnings->firstWhere('subject_code', 'ENG1')->absence_count);
    }

    public function test_only_the_students_current_section_time_in_absences_are_counted(): void
    {
        [$user, $student, $schedule] = $this->studentWithSchedule();
        $this->createLogs($user, $schedule, 4, 'absent');
        $this->createLog($user, $schedule, 20, 'present');
        $this->createLog($user, $schedule, 21, 'late');
        $this->createLog($user, $schedule, 22, 'excused');
        $this->createLog($user, $schedule, 23, 'absent', 'time_out');

        $otherUser = User::factory()->create(['role_id' => $user->role_id]);
        $this->createLogs($otherUser, $schedule, 5, 'absent', 30);

        $otherSection = Section::create([
            'course_id' => $student->course_id, 'section_name' => 'BSIT-4B',
            'school_year' => '2026-2027', 'semester' => '1st',
        ]);
        $outsideSchedule = Schedule::create([
            'subject_id' => $schedule->subject_id, 'instructor_id' => $schedule->instructor_id,
            'section_id' => $otherSection->id, 'day' => 'tuesday',
            'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'Lab 2',
        ]);
        $this->createLogs($user, $outsideSchedule, 5, 'absent', 40);

        $this->assertTrue(app(StudentAbsenceWarningService::class)->forStudent($student)->isEmpty());

        $this->createLog($user, $schedule, 50, 'absent');
        $this->assertCount(1, app(StudentAbsenceWarningService::class)->forStudent($student));
    }

    private function studentWithSchedule(): array
    {
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-4A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $studentRole = Role::create(['role_name' => 'student']);
        $user = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active']);
        $student = Student::create(['user_id' => $user->id, 'student_no' => 'STU-001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'section_id' => $section->id, 'course_id' => $course->id, 'status' => 'active']);
        $instructorRole = Role::create(['role_name' => 'instructor']);
        $instructorUser = User::factory()->create(['role_id' => $instructorRole->id]);
        $instructor = Instructor::create(['user_id' => $instructorUser->id, 'department_id' => $department->id, 'employee_no' => 'INS-001', 'first_name' => 'Maria', 'last_name' => 'Santos']);
        $subject = Subject::create(['subject_code' => 'CAP2', 'subject_name' => 'Capstone 2', 'units' => 3]);
        $schedule = Schedule::create(['subject_id' => $subject->id, 'instructor_id' => $instructor->id, 'section_id' => $section->id, 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => 'Lab 1']);

        return [$user, $student, $schedule];
    }

    private function createLogs(User $user, Schedule $schedule, int $count, string $status, int $offset = 0): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $this->createLog($user, $schedule, $offset + $index, $status);
        }
    }

    private function createLog(User $user, Schedule $schedule, int $day, string $status, string $type = 'time_in'): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $user->id, 'schedule_id' => $schedule->id,
            'attendance_type' => $type, 'scan_time' => Carbon::parse('2026-01-01')->addDays($day),
            'scan_key' => "test:{$user->id}:{$schedule->id}:{$day}:{$type}", 'status' => $status,
        ]);
    }
}
