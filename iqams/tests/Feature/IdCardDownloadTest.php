<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdCardDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_receives_an_id_card_payload_using_their_exact_assigned_qr_code(): void
    {
        $department = Department::create(['department_code' => 'IS', 'department_name' => 'Information Systems']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIS', 'course_name' => 'BS Information Systems']);
        $user = $this->user('student', ['avatar_path' => 'avatars/student.png']);
        Student::create([
            'user_id' => $user->id,
            'student_no' => '2026-0001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'course_id' => $course->id,
            'year_level' => 3,
            'qr_code' => ' EXACT-STUDENT-QR-9f4c ',
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson(route('id-card.show'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'name' => 'John Doe',
                'role' => 'Student',
                'identifier_label' => 'Student ID',
                'identifier' => '2026-0001',
                'department' => 'Information Systems',
                'year_level' => '3rd Year',
                'qr_code' => ' EXACT-STUDENT-QR-9f4c ',
                'filename' => 'STUDENT-2026-0001-IQAMS-ID.png',
            ])
            ->assertJsonPath('avatar_url', asset('storage/avatars/student.png'));

        $this->get(route('student.qr'))
            ->assertOk()
            ->assertSee('Download ID Card')
            ->assertSee(route('id-card.show'), false);
    }

    public function test_instructor_payload_contains_teaching_fields_and_never_a_year_level(): void
    {
        $department = Department::create(['department_code' => 'CS', 'department_name' => 'Computer Science']);
        $user = $this->user('instructor');
        Instructor::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'qr_code' => 'EXACT-INSTRUCTOR-QR',
        ]);

        $this->actingAs($user)->getJson(route('id-card.show'))
            ->assertOk()
            ->assertJson([
                'role' => 'Teaching Personnel',
                'identifier_label' => 'Employee ID',
                'identifier' => 'EMP001',
                'department' => 'Computer Science',
                'year_level' => null,
                'qr_code' => 'EXACT-INSTRUCTOR-QR',
                'filename' => 'INSTRUCTOR-EMP001-IQAMS-ID.png',
            ]);

        $this->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertSee('Download ID Card')
            ->assertSee(route('id-card.show'), false);
    }

    public function test_staff_payload_contains_non_teaching_fields_and_never_a_year_level(): void
    {
        $department = Department::create(['department_code' => 'HR', 'department_name' => 'Human Resources']);
        $user = $this->user('staff');
        NonTeachingStaff::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP002',
            'first_name' => 'Alex',
            'last_name' => 'Reyes',
            'qr_code' => 'EXACT-STAFF-QR',
        ]);

        $this->actingAs($user)->getJson(route('id-card.show'))
            ->assertOk()
            ->assertJson([
                'role' => 'Non-Teaching Personnel',
                'identifier_label' => 'Employee ID',
                'identifier' => 'EMP002',
                'department' => 'Human Resources',
                'year_level' => null,
                'qr_code' => 'EXACT-STAFF-QR',
                'filename' => 'STAFF-EMP002-IQAMS-ID.png',
            ]);

        $this->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Download ID Card');
    }

    public function test_missing_optional_data_uses_safe_fallbacks(): void
    {
        $department = Department::create(['department_code' => 'TBD', 'department_name' => '']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'TBD', 'course_name' => 'Unassigned']);
        $user = $this->user('student');
        Student::create([
            'user_id' => $user->id,
            'student_no' => 'STU001',
            'first_name' => 'No',
            'last_name' => 'Department',
            'course_id' => $course->id,
            'qr_code' => 'STUDENT-QR',
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson(route('id-card.show'))
            ->assertOk()
            ->assertJson([
                'department' => 'Department not assigned',
                'year_level' => null,
                'avatar_url' => asset('images/default-avatar.svg'),
            ]);
    }

    public function test_missing_qr_code_returns_an_error_instead_of_using_the_identifier(): void
    {
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $user = $this->user('instructor');
        Instructor::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP-NO-QR',
            'first_name' => 'Missing',
            'last_name' => 'Code',
            'qr_code' => null,
        ]);

        $this->actingAs($user)->getJson(route('id-card.show'))
            ->assertStatus(422)
            ->assertJson(['message' => 'No QR code is assigned to your account.']);
    }

    public function test_endpoint_requires_authentication_and_does_not_accept_a_user_identifier(): void
    {
        $this->getJson(route('id-card.show'))->assertUnauthorized();
        $this->getJson('/my-id-card/999')->assertNotFound();
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
            'status' => 'active',
        ], $attributes));
    }
}
