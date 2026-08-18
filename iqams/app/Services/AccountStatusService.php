<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountStatusService
{
    public const INACTIVE_MESSAGE = 'Attendance unavailable. Your account is inactive. Please contact the administrator.';

    public function ensureAccountIsActive(User $user, string $errorKey = 'attendance'): void
    {
        if (! $user->isAccountActive()) {
            throw ValidationException::withMessages([$errorKey => self::INACTIVE_MESSAGE]);
        }
    }
}
