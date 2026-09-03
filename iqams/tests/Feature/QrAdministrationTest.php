<?php

namespace Tests\Feature;

use App\Jobs\IssueMissingQrCredentials;
use App\Models\Course;
use App\Models\Department;
use App\Models\QrCredential;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\AuditLog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QrAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_queue_filtered_missing_qr_issuance(): void
    {
        Bus::fake();
        $admin = $this->user('admin');
        $student = $this->student();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('scanner-security.qr.batch'), ['role' => 'student', 'course_id' => $student->course_id])
            ->assertRedirect()
            ->assertSessionHas('success');

        Bus::assertBatched(fn ($batch) => $batch->name === 'IQAMS missing QR credential issuance');
    }

    public function test_batch_job_issues_missing_credentials_and_skips_existing_ones(): void
    {
        $admin = $this->user('admin');
        $student = $this->student();
        $existing = $this->student('QR-EXISTING');
        app(\App\Services\QrCredentialService::class)->issue($existing->user, $admin);

        app(IssueMissingQrCredentials::class, ['filters' => ['role' => 'student'], 'administratorId' => $admin->id])
            ->handle(app(\App\Services\QrCredentialService::class), app(\App\Services\AuditLogger::class));

        $this->assertDatabaseHas('qr_credentials', ['user_id' => $student->user_id, 'status' => 'active']);
        $this->assertSame(1, QrCredential::where('user_id', $existing->user_id)->where('status', 'active')->count());
        $batchAudit = AuditLog::query()->where('action', 'qr.batch_completed')->latest('id')->firstOrFail();
        $this->assertSame(1, $batchAudit->metadata['issued']);
        $this->assertSame(1, $batchAudit->metadata['skipped']);
        $this->assertSame(0, $batchAudit->metadata['failed']);
    }

    public function test_admin_id_card_endpoint_is_protected_and_returns_a_qr_payload(): void
    {
        $admin = $this->user('admin');
        $student = $this->student();
        app(\App\Services\QrCredentialService::class)->issue($student->user, $admin);

        $this->actingAs($admin)->getJson(route('admin.id-card.show', $student->user))
            ->assertOk()
            ->assertJsonStructure(['name', 'identifier', 'qr_code', 'avatar_url', 'logo_url', 'department', 'course', 'section', 'office']);

        $this->actingAs($this->user('student'))->getJson(route('admin.id-card.show', $student->user))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::findByName($role, 'web')->id]);
    }

    private function student(string $studentNo = 'QR-STUDENT'): Student
    {
        $department = Department::firstOrCreate(['department_code' => 'QR'], ['department_name' => 'QR Department']);
        $course = Course::firstOrCreate(['course_code' => 'QR101'], ['department_id' => $department->id, 'course_name' => 'QR Course']);
        $section = Section::firstOrCreate(['course_id' => $course->id, 'section_name' => 'QR-A'], ['school_year' => '2026-2027', 'semester' => '1st']);
        $user = $this->user('student');

        return Student::create([
            'user_id' => $user->id, 'student_no' => $studentNo, 'first_name' => 'QR', 'last_name' => 'Student',
            'course_id' => $course->id, 'section_id' => $section->id, 'status' => 'active',
        ])->load('user');
    }
}
