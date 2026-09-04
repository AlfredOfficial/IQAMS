<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Interactively create a permanent admin account';

    private const ADMIN_PERMISSIONS = [
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

    public function handle(): int
    {
        $this->info('Create a new admin account.');

        $name = $this->ask('Full name');
        $username = $this->ask('Username');
        $email = $this->ask('Email');
        $password = $this->secret('Password (input hidden)');
        $passwordConfirm = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Could not create admin:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }

        if ($password !== $passwordConfirm) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['role_name' => 'admin'],
        );
        $permissions = collect(self::ADMIN_PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
        );
        $adminRole->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->saveQuietly();

        app(RoleAssignmentService::class)->assign($user, 'admin');
        app(AuditLogger::class)->record('account.created', $user, ['role' => 'admin']);

        $this->info("Admin account created successfully: {$user->email}");

        return self::SUCCESS;
    }
}
