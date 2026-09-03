<?php

namespace Tests\Feature;

use App\Jobs\SendPasswordResetLink;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAccountProtectionService;
use App\Services\AuditLogger;
use App\Services\QrCredentialService;
use App\Services\RoleAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class Phase2IdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_legacy_role_changes_do_not_override_the_spatie_role(): void
    {
        $student = $this->user('student');
        $student->forceFill(['role_id' => Role::findByName('instructor', 'web')->id])->saveQuietly();

        $this->assertSame('student', $student->fresh()->primaryRoleName());
    }

    public function test_role_assignment_updates_the_compatibility_mirror_and_audit_log(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');

        app(RoleAssignmentService::class)->assign($student, 'staff', $admin);

        $this->assertTrue($student->fresh()->hasRole('staff'));
        $this->assertSame(Role::findByName('staff', 'web')->id, $student->fresh()->role_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.assigned', 'subject_id' => $student->id]);
    }

    public function test_the_final_active_administrator_cannot_be_changed_or_deleted(): void
    {
        $admin = $this->user('admin');
        $protection = app(AdminAccountProtectionService::class);

        $this->expectException(ValidationException::class);
        $protection->assertCanChangeStatus($admin, 'inactive');
    }

    public function test_forced_password_change_clears_the_reset_state(): void
    {
        $user = $this->user('student');
        $user->forceFill(['must_change_password' => true, 'password_changed_at' => null])->saveQuietly();

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertRedirect(route('password.force-change'));

        $this->actingAs($user)->put(route('password.force-change.update'), [
            'current_password' => 'password',
            'password' => 'A-new-password-123',
            'password_confirmation' => 'A-new-password-123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('A-new-password-123', $user->password));
    }

    public function test_flagged_accounts_are_sent_to_password_change_immediately_after_login(): void
    {
        $user = $this->user('instructor');
        $user->forceFill(['must_change_password' => true])->saveQuietly();

        $this->post('/login', [
            'user_id' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('password.force-change'));
    }

    public function test_forced_password_change_rejects_reusing_the_temporary_password(): void
    {
        $user = $this->user('staff');
        $user->forceFill(['must_change_password' => true])->saveQuietly();

        $this->actingAs($user)
            ->from(route('password.force-change'))
            ->put(route('password.force-change.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('password.force-change'))
            ->assertSessionHasErrorsIn('forcePasswordChange', 'password');
    }

    public function test_reset_delivery_job_uses_the_existing_password_broker(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        (new SendPasswordResetLink($user->id))->handle();

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_reset_command_is_safe_in_dry_run_mode(): void
    {
        $student = $this->user('student');
        $this->user('instructor');

        $this->artisan('accounts:require-password-reset --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry run:');

        $this->assertFalse($student->fresh()->must_change_password);
        Queue::fake();

        $this->artisan('accounts:require-password-reset --send')->assertExitCode(0);

        $this->assertTrue($student->fresh()->must_change_password);
        Queue::assertPushed(SendPasswordResetLink::class);
    }

    public function test_qr_rotation_revokes_the_old_credential_without_printing_a_value(): void
    {
        $student = $this->user('student');
        $credentials = app(QrCredentialService::class);
        $old = $credentials->issue($student);
        $oldValue = $credentials->plainText($old);

        $this->artisan('qr:rotate --user='.$student->id)
            ->assertExitCode(0)
            ->expectsOutputToContain('Plaintext values were not displayed.');

        $this->assertDatabaseHas('qr_credentials', ['id' => $old->id, 'status' => 'revoked']);
        $new = $student->fresh()->qrCredentials()->where('status', 'active')->latest('id')->firstOrFail();
        $this->assertNotSame($oldValue, $credentials->plainText($new));
        $this->assertDatabaseHas('audit_logs', ['action' => 'qr.revoked', 'subject_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'qr.regenerated', 'subject_id' => $student->id]);
    }

    public function test_audit_metadata_filters_secrets_and_records_are_append_only(): void
    {
        $user = $this->user('admin');
        $log = app(AuditLogger::class)->record('test.audit', $user, [
            'password' => 'secret',
            'token' => 'reset-token',
            'code' => 'qr-value',
            'user_snapshot' => $user->toArray(),
            'visible' => 'kept',
        ], $user);

        $metadata = $log->fresh()->metadata;
        $this->assertSame('kept', $metadata['visible']);
        $this->assertSame('console', $metadata['source']);
        $this->assertArrayNotHasKey('password', $metadata['user_snapshot']);
        $this->assertArrayNotHasKey('remember_token', $metadata['user_snapshot']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_records_cannot_be_deleted(): void
    {
        $log = app(AuditLogger::class)->record('test.delete', $this->user('admin'), [], null);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_sensitive_admin_mutations_require_password_confirmation(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');

        $this->actingAs($admin)
            ->patch(route('users.status.update', $student), ['status' => 'inactive'])
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.status.update', $student), ['status' => 'inactive'])
            ->assertRedirect();

        $this->assertSame('inactive', $student->fresh()->status);
    }

    public function test_audit_log_page_is_read_only_and_filterable(): void
    {
        $admin = $this->user('admin');
        app(AuditLogger::class)->record('filterable.action', $admin, ['visible' => 'yes'], $admin);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'filterable.action']))
            ->assertOk()
            ->assertSee('Filterable Action')
            ->assertSee('Audit activity')
            ->assertSee('Visible')
            ->assertSee('yes')
            ->assertDontSee('{"visible":"yes"}');
    }

    public function test_audit_log_page_requires_its_explicit_permission(): void
    {
        $admin = $this->user('admin');
        Role::findByName('admin', 'web')->revokePermissionTo('view-audit-logs');

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => 'active',
        ]);
    }
}
