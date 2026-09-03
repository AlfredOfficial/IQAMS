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
use Throwable;

class GenerateDailyPersonnelExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public string $exportId) {}

    public function handle(
        PersonnelAttendanceReportService $reports,
        DailyPersonnelAttendanceExportService $renderer,
    ): void {
        $export = DB::transaction(function (): ?ReportExport {
            $export = ReportExport::query()->lockForUpdate()->find($this->exportId);

            if (! $export || $export->status !== ReportExport::STATUS_PENDING) {
                return null;
            }

            $export->update(['status' => ReportExport::STATUS_PROCESSING]);

            return $export;
        });

        if (! $export) {
            return;
        }

        try {
            $parameters = $export->parameters;
            $date = Carbon::createFromFormat('!Y-m-d', (string) $parameters['date'], config('app.timezone'))->startOfDay();
            $report = $reports->getDailyReport($date, $parameters['filters'] ?? []);
            $extension = $export->format === ReportExport::FORMAT_PDF ? 'pdf' : 'xlsx';
            $filename = 'daily-personnel-attendance-'.$date->toDateString().'.'.$extension;
            $path = 'report-exports/'.$export->id.'.'.$extension;
            $contents = $extension === 'pdf'
                ? $renderer->pdf($report)
                : $renderer->xlsx($report);

            if (! Storage::disk('local')->put($path, $contents)) {
                throw new \RuntimeException('The report file could not be stored.');
            }

            $export->forceFill([
                'status' => ReportExport::STATUS_COMPLETED,
                'path' => $path,
                'filename' => $filename,
                'error' => null,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('attendance.report_export_ttl_hours', 24)),
            ])->save();
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => $this->attempts() >= $this->tries
                    ? ReportExport::STATUS_FAILED
                    : ReportExport::STATUS_PENDING,
                'error' => $this->attempts() >= $this->tries ? 'Report generation failed.' : null,
            ])->save();

            throw $exception;
        }
    }
}
