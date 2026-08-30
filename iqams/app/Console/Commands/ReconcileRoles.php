<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ReconcileRoles extends Command
{
    protected $signature = 'roles:reconcile';

    protected $description = 'Read-only comparison of legacy and Spatie role assignments';

    public function handle(): int
    {
        $mismatches = 0;

        User::with(['role', 'roles'])->orderBy('id')->chunk(200, function ($users) use (&$mismatches) {
            foreach ($users as $user) {
                $legacy = $user->role?->role_name;
                $spatie = $user->getRoleNames();

                if ($spatie->count() !== 1 || $legacy !== $spatie->first()) {
                    $mismatches++;
                    $this->line("User {$user->id} ({$user->username}): legacy=".($legacy ?? 'none').', spatie='.($spatie->implode('|') ?: 'none'));
                }
            }
        });

        if ($mismatches > 0) {
            $this->error("{$mismatches} role mismatch(es) found. No data was changed.");

            return self::FAILURE;
        }

        $this->info('All users have exactly one matching legacy and Spatie role. No data was changed.');

        return self::SUCCESS;
    }
}
