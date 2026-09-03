<?php

namespace App\Services;

use App\Jobs\SendPasswordResetLink;
use App\Models\User;

class AccountInvitationService
{
    public function queue(User $user, ?User $actor = null): void
    {
        SendPasswordResetLink::dispatch($user->id)->afterCommit();

        app(AuditLogger::class)->record('account.invitation_queued', $user, [
            'delivery' => 'password_reset_broker',
            'expires_in_minutes' => (int) config('auth.passwords.users.expire', 60),
        ], $actor);
    }
}
