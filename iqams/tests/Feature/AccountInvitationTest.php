<?php

namespace Tests\Feature;

use App\Jobs\SendPasswordResetLink;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\QrCredentialService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_student_instructor_and_staff_creation_queue_private_setup_links(): void
    {
        Storage::fake('public');
        Queue::fake();

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
                'enrollment_type' => 'regular',
                'student_no' => 'INV-STU-001',
                'first_name' => 'Invited',
                'last_name' => 'Student',
                'email' => 'invited.student@example.test',
                'avatar' => $this->avatar('student.png'),
            ])
            ->assertRedirect(route('students.index'))
            ->assertSessionMissing('generated_password')
            ->assertSessionHas('success', 'Account created. A setup email has been queued.');

        $student = User::where('username', 'INV-STU-001')->firstOrFail();
        $this->assertTrue($student->must_change_password);
        $this->assertFalse(Hash::check('Student@INV-STU-001', $student->password));
        $this->assertNull($student->email_verified_at);
        Queue::assertPushed(SendPasswordResetLink::class, fn ($job) => $job->userId === $student->id && $job->afterCommit);

        $student->forceFill([
            'password' => Hash::make('permanent-password'),
            'must_change_password' => false,
        ])->saveQuietly();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('students.index'))
            ->post(route('users.password.reset', $student))
            ->assertRedirect(route('students.index'))
            ->assertSessionMissing('generated_password');

        $this->assertTrue($student->fresh()->must_change_password);
        $this->assertFalse(Hash::check('Student@INV-STU-001', $student->fresh()->password));
        $this->assertFalse(Hash::check('permanent-password', $student->fresh()->password));
        $this->assertSame(1, $student->fresh()->session_version);

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
            ->assertSessionMissing('generated_password');

        $instructor = User::where('username', 'INV-INS-001')->firstOrFail();
        $this->assertTrue($instructor->must_change_password);
        $this->assertFalse(Hash::check('Instructor@INV-INS-001', $instructor->password));
        $this->assertNull($instructor->email_verified_at);
        Queue::assertPushed(SendPasswordResetLink::class, fn ($job) => $job->userId === $instructor->id && $job->afterCommit);

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
            ->assertSessionMissing('generated_password');

        $staff = User::where('username', 'INV-STF-001')->firstOrFail();
        $this->assertTrue($staff->must_change_password);
        $this->assertFalse(Hash::check('Staff@INV-STF-001', $staff->password));
        $this->assertNull($staff->email_verified_at);
        Queue::assertPushed(SendPasswordResetLink::class, fn ($job) => $job->userId === $staff->id && $job->afterCommit);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => 'active',
        ]);
    }

    public function test_irregular_student_creation_preserves_enrollment_and_queues_setup(): void
    {
        Queue::fake();
        Storage::fake('public');
        $admin = $this->user('admin');
        $department = Department::create(['department_code' => 'EMAIL', 'department_name' => 'Email test']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'EMAIL', 'course_name' => 'Email test']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $teacher = Instructor::create(['user_id' => $this->user('instructor')->id, 'department_id' => $department->id, 'employee_no' => 'EMAIL-INS', 'first_name' => 'Email', 'last_name' => 'Teacher']);
        $subject = Subject::create(['subject_code' => 'EMAIL', 'subject_name' => 'Email test', 'units' => 3]);
        $schedule = Schedule::create(['subject_id' => $subject->id, 'section_id' => $section->id, 'instructor_id' => $teacher->id, 'recurring_schedule_group_id' => (string) Str::uuid(), 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room' => '101']);

        $this->actingAs($admin)->post(route('students.store'), [
            'course_id' => $course->id, 'section_id' => $section->id,
            'enrollment_type' => 'irregular', 'enrollment_group_ids' => [$schedule->recurring_schedule_group_id],
            'student_no' => 'EMAIL-IRR', 'first_name' => 'Email', 'last_name' => 'Student',
            'email' => 'irregular@example.test', 'avatar' => $this->avatar('irregular.png'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('students.index'))->assertSessionMissing('generated_password');
        $user = User::where('username', 'EMAIL-IRR')->firstOrFail();
        $this->assertSame('irregular', $user->student->enrollment_type);
        $this->assertSame([$schedule->recurring_schedule_group_id], $user->student->scheduleEnrollments()->pluck('recurring_schedule_group_id')->all());
        $this->assertNull($user->email_verified_at);
        Queue::assertPushed(SendPasswordResetLink::class, fn ($job) => $job->userId === $user->id && $job->afterCommit);
    }

    public function test_failed_account_creation_does_not_queue_an_invitation(): void
    {
        Queue::fake();
        Notification::fake();
        Storage::fake('public');
        $admin = $this->user('admin');
        $office = OfficeUnit::create(['code' => 'FAIL', 'name' => 'Failure test', 'is_active' => true]);
        $this->mock(QrCredentialService::class, function ($mock): void {
            $mock->shouldReceive('issue')->once()->andThrow(new \RuntimeException('Synthetic account rollback'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('non-teaching-staff.store'), [
                'office_unit_id' => $office->id, 'employee_no' => 'EMAIL-FAIL', 'first_name' => 'Email', 'last_name' => 'Failure',
                'email' => 'failure@example.test', 'avatar' => $this->avatar('failure.png'),
            ]);
            $this->fail('Account creation should roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic account rollback', $exception->getMessage());
        }
        $this->assertDatabaseMissing('users', ['username' => 'EMAIL-FAIL']);
        $this->assertCount(0, Storage::disk('public')->allFiles('avatars'));
        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    private function avatar(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
