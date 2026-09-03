<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\QrCredentialService;
use Illuminate\Console\Command;

class BackfillQrCredentials extends Command
{
    protected $signature = 'qr:backfill-credentials';

    protected $description = 'Issue random static QR credentials to attendance users that do not have one';

    public function handle(QrCredentialService $credentials): int
    {
        $count = 0;
        User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['student', 'instructor', 'staff'])->where('guard_name', 'web'))
            ->whereDoesntHave('qrCredentials', fn ($q) => $q->where('status', 'active'))
            ->chunkById(100, function ($users) use ($credentials, &$count) {
                foreach ($users as $user) {
                    $credentials->issue($user);
                    $count++;
                }
            });
        $this->info("Issued {$count} QR credential(s).");

        return self::SUCCESS;
    }
}
