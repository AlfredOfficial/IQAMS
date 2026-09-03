<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'manage-users',
        'manage-role-assignments',
        'manage-academic-structure',
        'manage-office-units',
        'manage-schedules',
        'manage-attendance',
        'operate-scanner',
        'manage-scanner-security',
        'review-leave-requests',
        'manage-school-events',
        'view-reports',
        'view-audit-logs',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->mapWithKeys(function (string $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            return [$name => $permission];
        });

        foreach (['admin', 'instructor', 'staff', 'student'] as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['role_name' => $name],
            );
        }

        Role::findByName('admin', 'web')->syncPermissions($permissions->values());
        Role::findByName('instructor', 'web')->syncPermissions([$permissions['view-reports']]);
        Role::findByName('staff', 'web')->syncPermissions([$permissions['view-reports']]);
        Role::findByName('student', 'web')->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
