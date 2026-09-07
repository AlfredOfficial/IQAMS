<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            ->reorder('id')
            ->chunkById(200, function ($exports) use (&$removed): void {
                foreach ($exports as $export) {
                    DB::transaction(function () use ($export, &$removed) {
                        $current = ReportExport::whereKey($export->id)->lockForUpdate()->first();
                        if (! $current || $current->expires_at?->isFuture() || $current->lease_expires_at?->isFuture()) {
                            return;
                        }
                        if ($current->path) {
                            Storage::disk('local')->delete($current->path);
                        }
                        Storage::disk('local')->deleteDirectory('report-exports/'.$current->id);
                        $current->delete();
                        $removed++;
                    });
                }
            }, 'id');

        // A process can die after writing but before publishing its artifact.
        $disk = Storage::disk('local');
        foreach ($disk->allFiles('report-exports') as $path) {
            if ($disk->lastModified($path) > now()->subSeconds(150)->timestamp) {
                continue;
            }
            $parts = explode('/', $path);
            if (count($parts) !== 3) {
                continue;
            }
            DB::transaction(function () use ($parts, $path, $disk) {
                $export = ReportExport::whereKey($parts[1])->lockForUpdate()->first();
                if ($export?->path === $path || ($export?->lease_expires_at?->isFuture() && pathinfo($parts[2], PATHINFO_FILENAME) === $export->claim_token)) {
                    return;
                }
                $disk->delete($path);
            });
        }

        $this->info("{$removed} expired report export(s) removed.");

        return self::SUCCESS;
    }
}
