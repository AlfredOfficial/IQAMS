<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertStatus(200)
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_login_screen_links_to_the_password_request_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('href="'.route('password.request').'"', false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $genericStatus = 'If an account exists with this email address, we have sent a password reset link.';

        $knownResponse = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);
        $unknownResponse = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'not-registered@example.com',
        ]);

        $knownResponse->assertRedirect('/forgot-password')->assertSessionHas('status', $genericStatus);
        $unknownResponse->assertRedirect('/forgot-password')->assertSessionHas('status', $genericStatus);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_request_validates_email_input(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        $this->from('/forgot-password')
            ->post('/forgot-password', [])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => "unknown{$attempt}@example.com"])
                ->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'unknown6@example.com'])
            ->assertTooManyRequests();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

            $response
                ->assertStatus(200)
                ->assertSee('action="'.route('password.store').'"', false)
                ->assertSee('name="token" value="'.$notification->token.'"', false)
                ->assertSee('name="email"', false)
                ->assertSee('value="'.$user->email.'"', false)
                ->assertSee('name="password"', false)
                ->assertSee('name="password_confirmation"', false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_reset_link_contains_the_broker_token_and_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $this->assertStringContainsString('/reset-password/'.$notification->token, $url);
            $this->assertSame($user->email, $query['email'] ?? null);

            return true;
        });
    }

    public function test_invalid_token_is_rejected_without_changing_the_password(): void
    {
        $user = User::factory()->create();

        $this->from('/reset-password/invalid-token')->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/reset-password/invalid-token')->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_successful_reset_replaces_only_password_state_and_token_cannot_be_reused(): void
    {
        $user = User::factory()->create()->refresh();
        $token = Password::createToken($user);
        $originalName = $user->name;
        $originalStatus = $user->status;
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];

        $this->post('/reset-password', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertSame($originalName, $user->name);
        $this->assertSame($originalStatus, $user->status);
        $this->assertFalse(Auth::attempt(['username' => $user->username, 'password' => 'password']));
        $this->assertTrue(Auth::attempt(['username' => $user->username, 'password' => 'new-secure-password']));
        Auth::logout();

        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }
}
