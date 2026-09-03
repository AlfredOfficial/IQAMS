<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyPersonnelExport;
use App\Models\ReportExport;
use App\Services\DailyPersonnelAttendanceReportFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function store(Request $request, DailyPersonnelAttendanceReportFilters $filters): JsonResponse
    {
        [$date, $reportFilters] = $filters->validate($request);
        $validated = $request->validate(['format' => ['required', 'in:pdf,xlsx']]);

        $export = DB::transaction(function () use ($request, $date, $reportFilters, $validated): ReportExport {
            $export = ReportExport::create([
                'requested_by' => $request->user()->id,
                'report_type' => ReportExport::TYPE_DAILY_PERSONNEL,
                'format' => $validated['format'],
                'parameters' => [
                    'date' => $date->toDateString(),
                    'filters' => $reportFilters,
                ],
                'status' => ReportExport::STATUS_PENDING,
                'expires_at' => now()->addHours((int) config('attendance.report_export_ttl_hours', 24)),
            ]);

            GenerateDailyPersonnelExport::dispatch($export->id)->afterCommit();

            return $export;
        });

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'status_url' => route('admin.report-exports.show', $export),
            'download_url' => null,
        ], 202);
    }

    public function show(Request $request, ReportExport $reportExport): JsonResponse
    {
        $this->authorizeOwner($request, $reportExport);

        $expired = $reportExport->expires_at->isPast();
        $completed = $reportExport->status === ReportExport::STATUS_COMPLETED && ! $expired;

        return response()->json([
            'id' => $reportExport->id,
            'status' => $expired ? 'expired' : $reportExport->status,
            'download_url' => $completed ? route('admin.report-exports.download', $reportExport) : null,
        ]);
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        $this->authorizeOwner($request, $reportExport);

        abort_unless(
            $reportExport->status === ReportExport::STATUS_COMPLETED
                && $reportExport->expires_at->isFuture()
                && $reportExport->path
                && Storage::disk('local')->exists($reportExport->path),
            404,
        );

        return Storage::disk('local')->download($reportExport->path, $reportExport->filename, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeOwner(Request $request, ReportExport $reportExport): void
    {
        abort_unless((int) $reportExport->requested_by === (int) $request->user()->id, 403);
    }
}
