<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_and_logout_are_audited(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['user_id' => $user->username, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_succeeded', 'subject_id' => $user->id]);

        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'subject_id' => $user->id]);
    }

    public function test_invalid_login_and_lockout_are_audited_without_password_data(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['user_id' => $user->username, 'password' => 'wrong-password']);
        }
        $this->post('/login', ['user_id' => $user->username, 'password' => 'wrong-password']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.lockout']);
        $this->assertFalse(AuditLog::query()->whereIn('action', ['auth.login_failed', 'auth.lockout'])->get()->contains(fn (AuditLog $log) => str_contains(json_encode($log->metadata), 'wrong-password')));
    }
}
