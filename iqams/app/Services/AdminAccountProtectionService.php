<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAccountProtectionService
{
    public const FINAL_ADMIN_MESSAGE = 'The final active administrator cannot be demoted, deactivated, or deleted.';

    /**
     * Must be called inside the transaction that applies the change.
     */
    public function assertCanChangeRole(User $target, string $newRole): User
    {
        return $this->assertAtLeastOneActiveAdminAfter($target, $newRole, null, false);
    }

    /**
     * Must be called inside the transaction that applies the change.
     */
    public function assertCanChangeStatus(User $target, string $newStatus): User
    {
        return $this->assertAtLeastOneActiveAdminAfter($target, null, $newStatus, false);
    }

    /**
     * Must be called inside the transaction that deletes the target.
     */
    public function assertCanDelete(User $target): User
    {
        return $this->assertAtLeastOneActiveAdminAfter($target, null, null, true);
    }

    public function activeAdminCount(): int
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
            ->count();
    }

    private function assertAtLeastOneActiveAdminAfter(
        User $target,
        ?string $newRole,
        ?string $newStatus,
        bool $deleting,
    ): User {
        $this->lockActiveAdministrators();

        $target = User::query()->lockForUpdate()->findOrFail($target->id)->load('roles');

        $isActiveAdmin = $target->status === 'active' && $target->hasRole('admin');
        $willRemainActiveAdmin = ! $deleting
            && ($newStatus ?? $target->status) === 'active'
            && ($newRole ?? $target->primaryRoleName()) === 'admin';

        if ($isActiveAdmin && ! $willRemainActiveAdmin && $this->lockedActiveAdminCount() <= 1) {
            throw ValidationException::withMessages(['admin' => self::FINAL_ADMIN_MESSAGE]);
        }

        return $target;
    }

    private function lockActiveAdministrators(): void
    {
        $ids = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
            ->pluck('users.id');

        if ($ids->isNotEmpty()) {
            User::query()->whereKey($ids->all())->lockForUpdate()->get();
        }
    }

    private function lockedActiveAdminCount(): int
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
            ->lockForUpdate()
            ->get()
            ->count();
    }
}
