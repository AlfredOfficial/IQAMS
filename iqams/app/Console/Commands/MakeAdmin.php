<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Interactively create a permanent admin account';

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

        $adminRole = Role::where('role_name', 'admin')->first();

        if (! $adminRole) {
            $this->error("The 'admin' role does not exist yet. Run your role seeder first.");
            return self::FAILURE;
        }

        $user = User::create([
            'role_id' => $adminRole->id,
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("Admin account created successfully: {$user->email}");

        return self::SUCCESS;
    }
}