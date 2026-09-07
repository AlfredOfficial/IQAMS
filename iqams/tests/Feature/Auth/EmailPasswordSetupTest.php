<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendPasswordResetLink;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountInvitationService;
use App\Services\PasswordSetupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class EmailPasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
    }

    private function user(string $role = 'student'): User
    {
        return User::factory()->unverified()->create(['role_id' => Role::findByName($role, 'web')->id]);
    }

    private function payload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'Chosen-password-123', 'password_confirmation' => 'Chosen-password-123'];
    }

    public function test_opening_a_link_does_not_consume_it_or_verify_email(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_setup_verifies_email_changes_password_and_invalidates_other_sessions(): void
    {
        $user = $this->user();
        $user->forceFill(['must_change_password' => true])->save();
        $remember = $user->remember_token;
        $token = Password::createToken($user);
        $this->post(route('password.store'), $this->payload($user, $token))->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(1, $user->session_version);
        $this->assertNotSame($remember, $user->remember_token);
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->post(route('password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->post('/login', ['user_id' => $user->username, 'password' => 'Chosen-password-123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, session('auth.session_version'));
    }

    public function test_resets_preserve_existing_email_verification_time(): void
    {
        $user = $this->user();
        $verifiedAt = now()->subDays(10)->startOfSecond();
        $user->forceFill(['email_verified_at' => $verifiedAt])->save();
        $this->post(route('password.store'), $this->payload($user, Password::createToken($user)))->assertSessionHasNoErrors();
        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
    }

    public function test_wrong_email_and_inactive_account_cannot_consume_a_token(): void
    {
        $user = $this->user();
        $other = $this->user();
        $token = Password::createToken($user);
        $this->post(route('password.store'), $this->payload($other, $token))->assertSessionHasErrors('email');
        $user->update(['status' => 'inactive']);
        $this->post(route('password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_throttled_resend_preserves_token_and_later_delivery_replaces_it(): void
    {
        $user = $this->user();
        $service = app(PasswordSetupService::class);
        $this->assertSame('submitted_to_transport', $service->send($user->id, 0));
        $first = Notification::sent($user, ResetPassword::class)->last()->token;
        $this->assertSame('throttled', $service->send($user->id, 0));
        $this->assertTrue(Password::tokenExists($user, $first));
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->travel(61)->seconds();
        $this->assertSame('submitted_to_transport', $service->send($user->id, 0));
        $second = Notification::sent($user, ResetPassword::class)->last()->token;
        $this->assertNotSame($first, $second);
        $this->assertFalse(Password::tokenExists($user, $first));
        $this->assertTrue(Password::tokenExists($user, $second));
    }

    public function test_old_queued_delivery_cannot_replace_a_link_after_reset_or_completion(): void
    {
        $user = $this->user();
        $user->forceFill(['session_version' => 2])->save();
        $this->assertSame('superseded', app(PasswordSetupService::class)->send($user->id, 0));
        Notification::assertNothingSent();
    }

    public function test_invitation_delivery_waits_for_commit_and_does_not_run_on_rollback(): void
    {
        $user = $this->user();
        DB::beginTransaction();
        app(AccountInvitationService::class)->queue($user);
        Notification::assertNothingSent();
        DB::rollBack();
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.invitation_queued']);

        DB::beginTransaction();
        app(AccountInvitationService::class)->queue($user);
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_admin_reset_revokes_password_tokens_and_existing_sessions(): void
    {
        Queue::fake();
        $admin = $this->user('admin');
        $user = $this->user();
        $oldToken = Password::createToken($user);
        $oldRemember = $user->remember_token;
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('users.password.reset', $user))->assertRedirect()->assertSessionMissing('generated_password');
        $user->refresh();
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertFalse(Hash::check('Student@'.$user->username, $user->password));
        $this->assertFalse(Password::tokenExists($user, $oldToken));
        $this->assertNotSame($oldRemember, $user->remember_token);
        $this->assertSame(1, $user->session_version);
        Queue::assertPushed(SendPasswordResetLink::class, fn ($job) => $job->userId === $user->id && $job->expectedSessionVersion === 1 && $job->afterCommit);
        $this->actingAs($user)->withSession(['auth.session_version' => 0])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_legacy_unstamped_sessions_work_only_at_version_zero(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('student.dashboard'));
        $user->forceFill(['session_version' => 1])->save();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_remembered_login_stamps_current_version_and_old_cookie_is_rejected(): void
    {
        $user = $this->user();
        $user->forceFill(['session_version' => 3])->save();
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $oldCookie = $user->id.'|'.$user->remember_token.'|'.$user->password;
        $this->withCookie($cookieName, $oldCookie)->get(route('dashboard'))->assertRedirect(route('student.dashboard'));
        $this->assertSame(3, session('auth.session_version'));

        app(PasswordSetupService::class)->consume($this->payload($user, Password::createToken($user)));
        Auth::forgetGuards();
        session()->flush();
        $this->withCookie($cookieName, $oldCookie)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_admin_reset_does_not_change_password_or_queue_mail(): void
    {
        Queue::fake();
        $admin = $this->user('admin');
        $user = $this->user();
        $user->update(['status' => 'inactive']);
        $hash = $user->password;
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('users.password.reset', $user))->assertUnprocessable();
        $this->assertSame($hash, $user->fresh()->password);
        Queue::assertNothingPushed();
    }

    public function test_email_contains_username_expiry_and_resend_instructions(): void
    {
        $user = $this->user();
        (new SendPasswordResetLink($user->id, 0))->handle();
        $message = Notification::sent($user, ResetPassword::class)->first()->toMail($user);
        $this->assertSame('IQAMS: Set your password', $message->subject);
        $this->assertStringContainsString($user->username, implode(' ', $message->introLines));
        $this->assertStringContainsString('60 minutes', implode(' ', $message->outroLines));
        $this->assertStringContainsString(route('password.request'), implode(' ', $message->outroLines));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_link_delivery']);
        $this->assertStringNotContainsString(Notification::sent($user, ResetPassword::class)->first()->token, AuditLog::all()->toJson());
    }

    public function test_delivery_failure_is_sanitized_preserves_old_token_and_can_retry(): void
    {
        $user = $this->user();
        $oldToken = Password::createToken($user);
        $this->travel(61)->seconds();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('private-delivery-token'));
        $job = new SendPasswordResetLink($user->id, 0);
        try {
            $job->handle();
            $this->fail('Delivery failure must reach the worker.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private-delivery-token', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertTrue(Password::tokenExists($user, $oldToken));
        $this->assertStringNotContainsString('private-delivery-token', AuditLog::all()->toJson());
        Notification::fake();
        $job->handle();
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertFalse(Password::tokenExists($user, $oldToken));
        $job->failed(new RuntimeException('private-delivery-token'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_link_delivery']);
        $this->assertSame(3, $job->tries);
    }

    public function test_log_transport_is_rejected_before_it_can_write_a_link(): void
    {
        config(['mail.default' => 'log']);
        $user = $this->user();
        try {
            (new SendPasswordResetLink($user->id, 0))->handle();
            $this->fail('Log mailer must not receive private setup links.');
        } catch (RuntimeException) {
            Notification::assertNothingSent();
            $this->assertDatabaseCount('password_reset_tokens', 0);
        }
    }

    public function test_validation_does_not_flash_tokens_or_passwords(): void
    {
        $user = $this->user();
        $payload = $this->payload($user, Password::createToken($user));
        $payload['password_confirmation'] = 'mismatch';
        $this->post(route('password.store'), $payload)->assertSessionHasErrors('password');
        $this->assertArrayNotHasKey('token', session()->getOldInput());
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
    }

    public function test_setup_email_renders_into_the_array_transport_without_network_delivery(): void
    {
        Notification::swap(new ChannelManager($this->app));
        $user = $this->user();
        (new SendPasswordResetLink($user->id, 0))->handle();
        /** @var Mailer $mailer */
        $mailer = Mail::mailer('array');
        /** @var ArrayTransport $transport */
        $transport = $mailer->getSymfonyTransport();
        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('IQAMS: Set your password', $message->getSubject());
        $this->assertStringContainsString($user->username, $message->getHtmlBody());
        $this->assertStringContainsString('60 minutes', $message->getHtmlBody());
    }

    public function test_legacy_queued_payload_without_session_version_can_be_restored(): void
    {
        $user = $this->user();
        $job = (new \ReflectionClass(SendPasswordResetLink::class))->newInstanceWithoutConstructor();
        $job->__unserialize(['userId' => $user->id]);
        $this->assertNull($job->expectedSessionVersion);
        $job->handle();
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
