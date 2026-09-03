<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';

    protected $description = 'Record that the Laravel scheduler is alive';

    public function handle(): int
    {
        Cache::put(
            config('operations.scheduler_heartbeat_key'),
            now()->timestamp,
            now()->addSeconds((int) config('operations.heartbeat_ttl_seconds', 300)),
        );

        return self::SUCCESS;
    }
}
