<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\DailyPersonnelAttendanceExportService;
use App\Services\PersonnelAttendanceReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateDailyPersonnelExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public array $backoff = [30, 120];

    public function __construct(public string $exportId) {}

    public function handle(PersonnelAttendanceReportService $reports, DailyPersonnelAttendanceExportService $renderer): void
    {
        $claim = (string) Str::uuid();
        $export = DB::transaction(function () use ($claim): ?ReportExport {
            $export = ReportExport::query()->lockForUpdate()->find($this->exportId);
            if (! $export || in_array($export->status, [ReportExport::STATUS_COMPLETED, ReportExport::STATUS_FAILED], true)) {
                return null;
            }
            if ($export->lease_expires_at?->isFuture()) {
                $this->release(max(1, (int) ceil(now()->diffInSeconds($export->lease_expires_at)) + 1));

                return null;
            }
            if ($export->expires_at?->isPast()) {
                $export->update(['status' => ReportExport::STATUS_FAILED, 'error' => 'The export request has expired.']);

                return null;
            }
            $export->update(['status' => ReportExport::STATUS_PROCESSING, 'claim_token' => $claim,
                'lease_expires_at' => now()->addSeconds(120), 'error' => null]);

            return $export;
        });
        if (! $export) {
            return;
        }

        $path = null;
        try {
            $parameters = $export->parameters;
            $date = Carbon::createFromFormat('!Y-m-d', (string) $parameters['date'], config('app.timezone'))->startOfDay();
            $report = $reports->getDailyReport($date, $parameters['filters'] ?? []);
            $extension = $export->format === ReportExport::FORMAT_PDF ? 'pdf' : 'xlsx';
            $path = 'report-exports/'.$export->id.'/'.$claim.'.'.$extension;
            $contents = $extension === 'pdf' ? $renderer->pdf($report) : $renderer->xlsx($report);
            if (! Storage::disk('local')->put($path, $contents)) {
                throw new \RuntimeException('The report file could not be stored.');
            }
            $published = DB::transaction(function () use ($claim, $path, $extension, $date): bool {
                $current = ReportExport::query()->lockForUpdate()->find($this->exportId);
                if (! $current || $current->claim_token !== $claim || $current->status !== ReportExport::STATUS_PROCESSING || ! $current->lease_expires_at?->isFuture()) {
                    return false;
                }
                $current->update(['status' => ReportExport::STATUS_COMPLETED, 'path' => $path,
                    'filename' => 'daily-personnel-attendance-'.$date->toDateString().'.'.$extension,
                    'error' => null, 'completed_at' => now(),
                    'expires_at' => now()->addHours((int) config('attendance.report_export_ttl_hours', 24)),
                    'claim_token' => null, 'lease_expires_at' => null]);

                return true;
            });
            if (! $published) {
                throw new \RuntimeException('The export processing lease expired.');
            }
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            ReportExport::whereKey($this->exportId)->where('claim_token', $claim)->where('status', ReportExport::STATUS_PROCESSING)
                ->update(['status' => $this->attempts() >= $this->tries ? ReportExport::STATUS_FAILED : ReportExport::STATUS_PENDING,
                    'error' => $this->attempts() >= $this->tries ? 'Report generation failed.' : null,
                    'claim_token' => null, 'lease_expires_at' => null]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        // The callback restores the original payload. Do not clear another worker's active lease.
        ReportExport::whereKey($this->exportId)->whereNotIn('status', [ReportExport::STATUS_COMPLETED, ReportExport::STATUS_FAILED])
            ->where(fn ($q) => $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now()))
            ->update(['status' => ReportExport::STATUS_FAILED, 'error' => 'Report generation failed.', 'claim_token' => null, 'lease_expires_at' => null]);
    }
}
