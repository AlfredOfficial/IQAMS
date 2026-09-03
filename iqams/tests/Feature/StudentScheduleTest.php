<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class StudentScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_schedule_is_available_on_a_dedicated_page(): void
    {
        $student = $this->studentAccount();

        $this->actingAs($student->user)
            ->get(route('student.schedule'))
            ->assertOk()
            ->assertViewIs('student.schedule')
            ->assertSee('Weekly schedule')
            ->assertSee('MAT101')
            ->assertSee('Mathematics')
            ->assertSee('Monday')
            ->assertSee('8:00 AM')
            ->assertSee(route('student.schedule'), false)
            ->assertDontSee('Recent attendance');
    }

    private function studentAccount(): Student
    {
        $studentRole = Role::firstOrCreate(['role_name' => 'student']);
        $instructorRole = Role::firstOrCreate(['role_name' => 'instructor']);
        $department = Department::create([
            'department_code' => 'SCH',
            'department_name' => 'Schedule Department',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'course_code' => 'BSIT',
            'course_name' => 'Information Technology',
        ]);
        $section = Section::create([
            'course_id' => $course->id,
            'section_name' => '1-A',
            'school_year' => '2026-2027',
            'semester' => '1st',
        ]);
        $instructorUser = User::factory()->create(['role_id' => $instructorRole->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'SCH-INS-001',
            'first_name' => 'Schedule',
            'last_name' => 'Instructor',
        ]);
        $subject = Subject::create([
            'subject_code' => 'MAT101',
            'subject_name' => 'Mathematics',
            'units' => 3,
        ]);
        Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Room 101',
        ]);
        $studentUser = User::factory()->create(['role_id' => $studentRole->id]);

        return Student::create([
            'user_id' => $studentUser->id,
            'student_no' => 'SCH-STU-001',
            'first_name' => 'Schedule',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
    }
}
