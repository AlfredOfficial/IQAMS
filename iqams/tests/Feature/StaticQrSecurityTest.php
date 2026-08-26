<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Role;
use App\Models\ScannerTerminal;
use App\Models\Student;
use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticQrSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_credentials_are_hashed_at_rest_and_can_be_revoked_and_replaced(): void
    {
        [$student, $admin] = $this->users();
        $service = app(QrCredentialService::class);
        $first = $service->issue($student, $admin);
        $plain = $service->plainText($first);

        $this->assertStringStartsWith('IQAMS-', $plain);
        $this->assertSame(hash('sha256', $plain), $first->code_hash);
        $this->assertStringNotContainsString($student->username, $plain);

        $replacement = $service->regenerate($student, $admin);
        $this->assertSame('revoked', $first->fresh()->status);
        $this->assertSame('active', $replacement->status);
        $this->assertNotSame($plain, $service->plainText($replacement));
    }

    public function test_scan_is_audited_and_does_not_trust_a_client_location(): void
    {
        [$student, $admin] = $this->users();
        $credential = app(QrCredentialService::class)->issue($student, $admin);
        $terminal = ScannerTerminal::create(['name' => 'Gate PC', 'location' => 'Main Gate']);

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), [
                'qr_code' => app(QrCredentialService::class)->plainText($credential),
                'scanner_location' => 'Forged location',
            ])->assertUnprocessable()->assertJsonPath('person.id', $student->id);

        $this->assertDatabaseCount('attendance_logs', 0);
        $this->assertDatabaseHas('attendance_scan_audits', [
            'user_id' => $student->id, 'admin_id' => $admin->id,
            'scanner_terminal_id' => $terminal->id, 'location' => 'Main Gate', 'outcome' => 'rejected',
        ]);
    }

    public function test_repeated_invalid_scans_create_an_anomaly_flag(): void
    {
        [, $admin] = $this->users();
        $terminal = ScannerTerminal::create(['name' => 'Gate PC', 'location' => 'Main Gate']);
        config(['attendance.invalid_scan_threshold' => 2]);
        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id]);

        $this->postJson(route('attendance-scanner.scan'), ['qr_code' => 'INVALID-ONE'])->assertUnprocessable();
        $this->postJson(route('attendance-scanner.scan'), ['qr_code' => 'INVALID-TWO'])->assertUnprocessable();
        $this->assertDatabaseHas('security_flags', ['category' => 'repeated_invalid_scans', 'status' => 'open']);
    }

    private function users(): array
    {
        $studentRole = Role::firstOrCreate(['role_name' => 'student']);
        $adminRole = Role::firstOrCreate(['role_name' => 'admin']);
        $department = Department::create(['department_code' => 'SEC', 'department_name' => 'Security']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSSEC', 'course_name' => 'Security']);
        $student = User::factory()->create(['role_id' => $studentRole->id, 'username' => 'STU-SEC-1', 'status' => 'active']);
        Student::create(['user_id' => $student->id, 'student_no' => 'STU-SEC-1', 'first_name' => 'Secure', 'last_name' => 'Student', 'course_id' => $course->id, 'qr_code' => 'LEGACY-SEC-1', 'status' => 'active']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        return [$student, $admin];
    }
}
