<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordSetupConcurrencyTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'iqams_password_setup_test'
            || config('database.connections.mysql.host') !== '127.0.0.1'
            || (string) config('database.connections.mysql.port') !== '13317'
            || config('database.connections.mysql.url')) {
            throw new \RuntimeException('This opt-in test requires the disposable MySQL database on port 13317.');
        }

        return $app;
    }

    public function test_simultaneous_submissions_consume_a_token_once(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $user = User::factory()->unverified()->create();
        $token = Password::createToken($user);
        $workers = [];
        $startAt = microtime(true) + 3;
        foreach (['First-password-123', 'Second-password-123'] as $password) {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/consume-password-token.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $this->assertIsResource($process);
            fwrite($pipes[0], json_encode(['start_at' => $startAt, 'credentials' => [
                'email' => $user->email, 'token' => $token, 'password' => $password,
            ]], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $workers[] = [$process, $pipes, $password];
        }
        $statuses = [];
        $winner = null;
        foreach ($workers as [$process, $pipes, $password]) {
            stream_set_timeout($pipes[1], 20);
            $output = stream_get_contents($pipes[1]);
            $metadata = stream_get_meta_data($pipes[1]);
            if ($metadata['timed_out']) {
                proc_terminate($process);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $this->assertFalse($metadata['timed_out'], 'Token consumption timed out.');
            $this->assertSame(0, $exit, 'Token worker failed.');
            $status = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['status'];
            $statuses[] = $status;
            if ($status === Password::PASSWORD_RESET) {
                $winner = $password;
            }
        }
        $this->assertEqualsCanonicalizing([Password::PASSWORD_RESET, Password::INVALID_TOKEN], $statuses);
        $this->assertTrue(Hash::check($winner, $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
