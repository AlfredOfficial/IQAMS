<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OperationsHealth extends Command
{
    protected $signature = 'ops:health';

    protected $description = 'Check database, shared cache, scheduler, and queue-worker health';

    public function handle(): int
    {
        $checks = [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'scheduler' => $this->heartbeat(config('operations.scheduler_heartbeat_key')),
            'queue' => $this->heartbeat(config('operations.queue_heartbeat_key')),
        ];
        $healthy = collect($checks)->every(fn (bool $result) => $result);

        foreach ($checks as $name => $result) {
            $this->line(sprintf('%-10s %s', $name, $result ? 'ok' : 'failed'));
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function database(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cache(): bool
    {
        try {
            $key = 'ops.health.probe.'.bin2hex(random_bytes(8));
            $value = bin2hex(random_bytes(8));
            Cache::put($key, $value, now()->addSeconds(10));

            return Cache::get($key) === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function heartbeat(string $key): bool
    {
        try {
            $timestamp = Cache::get($key);
            $maxAge = (int) config('operations.health_max_age_seconds', 120);

            return is_numeric($timestamp) && now()->timestamp - (int) $timestamp <= $maxAge;
        } catch (\Throwable) {
            return false;
        }
    }
}
