<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_listing_pages_do_not_render_credentials_without_a_flash_message(): void
    {
        $admin = $this->user('admin');

        foreach ([
            'students.index',
            'instructors.index',
            'non-teaching-staff.index',
        ] as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertDontSee('legacy-secret');
        }
    }

    public function test_student_instructor_and_staff_creation_use_role_specific_temporary_passwords(): void
    {
        Storage::fake('public');

        $admin = $this->user('admin');
        $department = Department::create([
            'department_code' => 'INV',
            'department_name' => 'Invitation Department',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'course_code' => 'INV101',
            'course_name' => 'Invitation Course',
        ]);
        $section = Section::create([
            'course_id' => $course->id,
            'section_name' => 'A',
            'school_year' => '2026-2027',
            'semester' => '1st',
        ]);
        $officeUnit = OfficeUnit::create([
            'code' => 'INV',
            'name' => 'Invitation Office',
            'is_active' => true,
        ]);

        $studentResponse = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('students.store'), [
                'course_id' => $course->id,
                'section_id' => $section->id,
                'student_no' => 'INV-STU-001',
                'first_name' => 'Invited',
                'last_name' => 'Student',
                'email' => 'invited.student@example.test',
                'avatar' => $this->avatar('student.png'),
            ])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('generated_password', 'Student@INV-STU-001')
            ->assertSessionHas('generated_username', 'INV-STU-001');

        $student = User::where('username', 'INV-STU-001')->firstOrFail();
        $this->assertTrue($student->must_change_password);
        $this->assertTrue(Hash::check('Student@INV-STU-001', $student->password));
        $this->assertStringContainsString('temporary credentials', strtolower((string) $studentResponse->getSession()->get('success')));

        $student->forceFill([
            'password' => Hash::make('permanent-password'),
            'must_change_password' => false,
        ])->saveQuietly();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('students.index'))
            ->post(route('users.password.reset', $student))
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('generated_password', 'Student@INV-STU-001');

        $this->assertTrue($student->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Student@INV-STU-001', $student->fresh()->password));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('instructors.store'), [
                'department_id' => $department->id,
                'employee_no' => 'INV-INS-001',
                'first_name' => 'Invited',
                'last_name' => 'Instructor',
                'email' => 'invited.instructor@example.test',
                'avatar' => $this->avatar('instructor.png'),
            ])
            ->assertRedirect(route('instructors.index'))
            ->assertSessionHas('generated_password', 'Instructor@INV-INS-001')
            ->assertSessionHas('generated_username', 'INV-INS-001');

        $instructor = User::where('username', 'INV-INS-001')->firstOrFail();
        $this->assertTrue($instructor->must_change_password);
        $this->assertTrue(Hash::check('Instructor@INV-INS-001', $instructor->password));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('non-teaching-staff.store'), [
                'office_unit_id' => $officeUnit->id,
                'employee_no' => 'INV-STF-001',
                'first_name' => 'Invited',
                'last_name' => 'Staff',
                'email' => 'invited.staff@example.test',
                'avatar' => $this->avatar('staff.png'),
            ])
            ->assertRedirect(route('non-teaching-staff.index'))
            ->assertSessionHas('generated_password', 'Staff@INV-STF-001')
            ->assertSessionHas('generated_username', 'INV-STF-001');

        $staff = User::where('username', 'INV-STF-001')->firstOrFail();
        $this->assertTrue($staff->must_change_password);
        $this->assertTrue(Hash::check('Staff@INV-STF-001', $staff->password));
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => 'active',
        ]);
    }

    private function avatar(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
