<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Console\Command;

class RotateQrCredentials extends Command
{
    protected $signature = 'qr:rotate
                            {--all : Rotate credentials for all active student, instructor, and staff accounts}
                            {--role= : Rotate credentials for one active portal role}
                            {--user= : Rotate credentials for one user ID}
                            {--dry-run : Report eligible accounts without changing credentials}';

    protected $description = 'Rotate active QR credentials without displaying their values';

    public function handle(QrCredentialService $credentials): int
    {
        $selectors = (int) (bool) $this->option('all')
            + (int) ($this->option('role') !== null)
            + (int) ($this->option('user') !== null);

        if ($selectors !== 1) {
            $this->error('Exactly one of --all, --role, or --user is required.');

            return self::INVALID;
        }

        $query = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($roles) => $roles
                ->whereIn('name', ['student', 'instructor', 'staff'])
                ->where('guard_name', 'web'));

        if ($this->option('role') !== null) {
            $role = strtolower(trim((string) $this->option('role')));

            if (! in_array($role, ['student', 'instructor', 'staff'], true)) {
                $this->error('The --role value must be student, instructor, or staff.');

                return self::INVALID;
            }

            $query->whereHas('roles', fn ($roles) => $roles
                ->where('name', $role)
                ->where('guard_name', 'web'));
        }

        if ($this->option('user') !== null) {
            $userId = (string) $this->option('user');

            if (! ctype_digit($userId) || (int) $userId < 1) {
                $this->error('The --user value must be a positive user ID.');

                return self::INVALID;
            }

            $query->whereKey((int) $userId);
        }

        if ($this->option('user') !== null && ! (clone $query)->exists()) {
            $this->error('The requested user is not an active student, instructor, or staff account.');

            return self::FAILURE;
        }

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} active account(s) are eligible for QR rotation.");

            return self::SUCCESS;
        }

        $rotated = 0;
        $query->chunkById(100, function ($users) use ($credentials, &$rotated) {
            foreach ($users as $user) {
                $credentials->regenerate($user);
                $rotated++;
            }
        });

        $this->info("Rotated {$rotated} QR credential(s). Plaintext values were not displayed.");

        return self::SUCCESS;
    }
}
