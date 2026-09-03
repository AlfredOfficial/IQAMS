<?php

namespace App\Console\Commands;

use App\Services\IntegrityReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IntegrityReport extends Command
{
    protected $signature = 'integrity:report {--format=table} {--output=}';

    protected $description = 'Report attendance, leave, schedule, and academic integrity exceptions';

    public function handle(IntegrityReportService $reporter): int
    {
        $report = $reporter->report();
        $format = strtolower((string) $this->option('format'));
        $payload = $format === 'json'
            ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $this->tableOutput($report);

        if ($this->option('output')) {
            File::put((string) $this->option('output'), $payload.PHP_EOL);
            $this->info('Integrity report written to the requested external path.');
        } else {
            $this->line($payload);
        }

        return self::SUCCESS;
    }

    private function tableOutput(array $report): string
    {
        return collect($report)
            ->map(fn (array $items, string $name) => $name.': '.count($items))
            ->implode(PHP_EOL);
    }
}
