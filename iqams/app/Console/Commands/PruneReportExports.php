<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneReportExports extends Command
{
    protected $signature = 'reports:prune-exports';

    protected $description = 'Remove expired private report-export artifacts';

    public function handle(): int
    {
        $removed = 0;

        ReportExport::query()
            ->where('expires_at', '<=', now())
            ->orderBy('created_at')
            ->chunkById(200, function ($exports) use (&$removed): void {
                foreach ($exports as $export) {
                    if ($export->path) {
                        Storage::disk('local')->delete($export->path);
                    }

                    $export->delete();
                    $removed++;
                }
            }, 'id');

        $this->info("{$removed} expired report export(s) removed.");

        return self::SUCCESS;
    }
}
