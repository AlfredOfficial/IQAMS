<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstructorNameFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_instructor_with_conventionally_formatted_name_fields(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        Role::firstOrCreate(['role_name' => 'instructor']);
        $department = Department::create([
            'department_code' => 'TED',
            'department_name' => 'Teacher Education',
        ]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('instructors.store'), [
            'department_id' => $department->id,
            'employee_no' => 'INS-2026-001',
            'name_prefix' => 'Engr.',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'professional_credentials' => 'LPT, MATVE',
            'email' => 'juan@example.test',
            'avatar' => UploadedFile::fake()->createWithContent(
                'juan.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
        ])->assertRedirect(route('instructors.index'));

        $instructor = Instructor::firstOrFail();
        $this->assertSame('Engr. Juan Santos Dela Cruz, LPT, MATVE', $instructor->fullName());
        $this->assertSame($instructor->fullName(), $instructor->user->name);
        $this->assertDatabaseHas('instructors', [
            'name_prefix' => 'Engr.',
            'middle_name' => 'Santos',
            'professional_credentials' => 'LPT, MATVE',
        ]);
    }

    public function test_admin_can_update_instructor_name_fields_and_sync_the_user_name(): void
    {
        $admin = $this->user('admin');
        $instructorUser = $this->user('instructor');
        $department = Department::create([
            'department_code' => 'ENG',
            'department_name' => 'Engineering',
        ]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-2026-002',
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'qr_code' => 'INS-2026-002',
        ]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put(route('instructors.update', $instructor), [
            'department_id' => $department->id,
            'name_prefix' => 'Dr.',
            'first_name' => 'Maria',
            'middle_name' => 'Lopez',
            'last_name' => 'Reyes',
            'professional_credentials' => 'LPT',
        ])->assertRedirect(route('instructors.index'));

        $this->assertSame('Dr. Maria Lopez Reyes, LPT', $instructor->fresh()->fullName());
        $this->assertSame('Dr. Maria Lopez Reyes, LPT', $instructorUser->fresh()->name);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
            'status' => 'active',
        ]);
    }
}
