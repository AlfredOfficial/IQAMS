<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use SensitiveParameter;

class PasswordSetupService
{
    public function send(int $userId, ?int $expectedSessionVersion = null): string
    {
        // Keep issuance, transport submission and token replacement atomic with
        // consumption/admin resets. A failed transport rolls back token changes.
        return DB::transaction(function () use ($userId, $expectedSessionVersion): string {
            $user = User::query()->lockForUpdate()->find($userId);
            if (! $user || ! $user->isAccountActive()) {
                app(AuditLogger::class)->record('account.password_link_delivery', $user, ['outcome' => 'inactive_or_missing']);

                return 'inactive_or_missing';
            }
            if ($expectedSessionVersion !== null && $expectedSessionVersion !== (int) $user->session_version) {
                app(AuditLogger::class)->record('account.password_link_delivery', $user, ['outcome' => 'superseded']);

                return 'superseded';
            }

            if ($this->usesLogTransport((string) config('mail.default'))) {
                throw new RuntimeException('Configure a mail transport that does not log password setup links.');
            }

            $status = Password::broker()->sendResetLink(['email' => $user->email, 'status' => 'active']);
            $outcome = match ($status) {
                Password::RESET_LINK_SENT => 'submitted_to_transport',
                Password::RESET_THROTTLED => 'throttled',
                default => 'inactive_or_missing',
            };
            app(AuditLogger::class)->record('account.password_link_delivery', $user, [
                'outcome' => $outcome,
                'mailer' => config('mail.default'),
            ]);

            return $outcome;
        });
    }

    public function consume(#[SensitiveParameter] array $credentials): string
    {
        return DB::transaction(function () use ($credentials): string {
            $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
            if (! $user || ! $user->isAccountActive()) {
                return Password::INVALID_USER;
            }

            return Password::broker()->reset($credentials + ['status' => 'active'], function (User $user, #[SensitiveParameter] string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'session_version' => (int) $user->session_version + 1,
                ])->save();

                app(AuditLogger::class)->record('account.password_changed', $user, ['source' => 'email_link'], $user);
                event(new PasswordReset($user));
                // The broker deletes the token before the enclosing transaction commits.
            });
        });
    }

    private function usesLogTransport(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return true;
        }
        $settings = config('mail.mailers.'.$mailer, []);
        if (($settings['transport'] ?? null) === 'log') {
            return true;
        }
        foreach ($settings['mailers'] ?? [] as $child) {
            if ($this->usesLogTransport($child, [...$visited, $mailer])) {
                return true;
            }
        }

        return false;
    }
}
