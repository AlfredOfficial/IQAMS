<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleAssignmentService
{
    public const PORTAL_ROLES = ['admin', 'instructor', 'staff', 'student'];

    public function assign(User $user, string $roleName, ?User $actor = null): void
    {
        if (! in_array($roleName, self::PORTAL_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'The selected role is invalid.']);
        }

        if ($actor?->is($user)) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        $currentRole = $user->getRoleNames()->first() ?? $user->role?->role_name;
        if ($currentRole === 'admin' && $roleName !== 'admin' && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages(['role' => 'The final active administrator cannot be reassigned.']);
        }

        DB::transaction(function () use ($user, $roleName) {
            $role = Role::findByName($roleName, 'web');
            $user->syncRoles([$role]);
            $user->forceFill(['role_id' => $role->id])->save();
        });

        $user->unsetRelation('role')->unsetRelation('roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeAdminCount(): int
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
            ->count();
    }
}
