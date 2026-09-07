<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordSetupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendPasswordResetLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    // Explicit property default also applies when older queued payloads are
    // restored without calling the constructor.
    public ?int $expectedSessionVersion = null;

    public function __construct(public int $userId, ?int $expectedSessionVersion = null)
    {
        $this->expectedSessionVersion = $expectedSessionVersion;
    }

    public function handle(): void
    {
        try {
            app(PasswordSetupService::class)->send($this->userId, $this->expectedSessionVersion);
        } catch (Throwable) {
            app(AuditLogger::class)->record('account.password_link_delivery', User::find($this->userId), [
                'outcome' => 'attempt_failed',
                'attempt' => $this->attempts(),
            ]);
            // Transport exceptions may contain message bodies or URLs. Do not
            // include the original exception in logs or failed-job records.
            throw new RuntimeException('Password setup email delivery failed. Check the mail transport configuration.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditLogger::class)->record('account.password_link_delivery', User::find($this->userId), ['outcome' => 'failed']);
    }
}
