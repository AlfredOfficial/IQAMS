<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountInvitationService;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RequirePasswordReset extends Command
{
    protected $signature = 'accounts:require-password-reset
                            {--dry-run : Report accounts without changing state}
                            {--send : Mark accounts and queue reset invitations}';

    protected $description = 'Require active human accounts to choose a new password';

    public function handle(AccountInvitationService $invitations, AuditLogger $audit): int
    {
        if ($this->option('dry-run') && $this->option('send')) {
            $this->error('Use either --dry-run or --send, not both.');

            return self::INVALID;
        }

        $send = (bool) $this->option('send');
        $total = 0;
        $alreadyRequired = 0;
        $changed = 0;
        $queued = 0;

        User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query
                ->whereIn('name', ['admin', 'instructor', 'staff', 'student'])
                ->where('guard_name', 'web'))
            ->chunkById(200, function ($users) use ($send, $invitations, $audit, &$total, &$alreadyRequired, &$changed, &$queued) {
                foreach ($users as $user) {
                    $total++;

                    if ($user->must_change_password) {
                        $alreadyRequired++;
                        continue;
                    }

                    if (! $send) {
                        $changed++;
                        continue;
                    }

                    $didChange = false;
                    DB::transaction(function () use ($user, $audit, &$didChange) {
                        $lockedUser = User::query()->lockForUpdate()->find($user->id);

                        if (! $lockedUser || $lockedUser->must_change_password) {
                            return;
                        }

                        $lockedUser->forceFill(['must_change_password' => true])->save();
                        $didChange = true;
                        $audit->record(
                            'account.password_reset_required',
                            $lockedUser,
                            ['source' => 'console'],
                            null,
                        );
                    });

                    if ($didChange) {
                        $changed++;
                        $invitations->queue($user);
                        $queued++;
                    }
                }
            });

        if ($send) {
            $this->info("Processed {$total} active human account(s); marked {$changed} for reset and queued {$queued} invitation(s).");
        } else {
            $this->info("Dry run: {$total} active human account(s); {$changed} require a reset and {$alreadyRequired} already require one.");
        }

        return self::SUCCESS;
    }
}
