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

        DB::transaction(function () use ($user, $roleName) {
            $lockedUser = app(AdminAccountProtectionService::class)->assertCanChangeRole($user, $roleName);

            $role = Role::findByName($roleName, 'web');
            $lockedUser->syncRoles([$role]);
            $lockedUser->forceFill(['role_id' => $role->id])->saveQuietly();
        });

        $user->unsetRelation('role')->unsetRelation('roles');
        DashboardReferenceCache::forget();
        app(AttendanceSummaryCache::class)->invalidateAll();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(AuditLogger::class)->record('role.assigned', $user, [
            'role' => $roleName,
            'compatibility_mirror_updated' => true,
        ], $actor);
    }
}
