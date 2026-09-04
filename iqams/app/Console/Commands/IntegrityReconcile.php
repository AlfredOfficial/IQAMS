<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceSummaryCache;
use App\Services\AuditLogger;
use App\Services\IntegrityKeyService;
use App\Services\IntegrityReportService;
use App\Services\LeaveOverlapService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IntegrityReconcile extends Command
{
    protected $signature = 'integrity:reconcile {--dry-run} {--apply} {--manifest=}';

    protected $description = 'Preview or apply reviewed integrity reconciliation decisions';

    public function handle(
        IntegrityReportService $reporter,
        IntegrityKeyService $keys,
        LeaveOverlapService $leaveOverlaps,
        AuditLogger $audit,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Choose exactly one of --dry-run or --apply.');

            return self::INVALID;
        }

        if ($dryRun) {
            $counts = $reporter->counts();
            foreach ($counts as $name => $count) {
                $this->line($name.': '.$count);
            }
            $this->info('Dry run complete. No records were changed.');

            return self::SUCCESS;
        }

        $manifestPath = $this->option('manifest');
        if (! $manifestPath || ! File::exists($manifestPath)) {
            $this->error('An existing reviewed manifest is required with --apply --manifest=...');

            return self::INVALID;
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        if (! is_array($manifest)) {
            $this->error('The reconciliation manifest must contain a JSON object.');

            return self::INVALID;
        }

        try {
            $this->assertManifestCoversCurrentExceptions($reporter, $leaveOverlaps, $manifest);
            $this->reconcileAttendance($reporter, $keys, $manifest['attendance'] ?? []);
            $this->reconcileLeave($leaveOverlaps, $manifest['leave'] ?? [], $audit);
            $this->reconcilePlacements($manifest['placements'] ?? []);
            $this->reconcileSections($reporter, $manifest['sections'] ?? [], $audit);
            $this->reconcileSchedules($reporter, $manifest['schedules'] ?? [], $audit);
            $this->backfillReferenceKeys($keys);
            // Several reconciliation writes intentionally use saveQuietly() or
            // query-builder updates, so model observers cannot invalidate the
            // summary caches for them.
            app(AttendanceSummaryCache::class)->invalidateAll();
            $audit->record('integrity.reconciled', null, [
                'attendance_groups' => count($manifest['attendance'] ?? []),
                'leave_groups' => count($manifest['leave'] ?? []),
                'placement_decisions' => count($manifest['placements'] ?? []),
                'section_groups' => count($manifest['sections'] ?? []),
                'schedule_groups' => count($manifest['schedules'] ?? []),
            ]);
        } catch (RuntimeException|ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Integrity reconciliation applied without printing credentials or secrets.');

        return self::SUCCESS;
    }

    private function assertManifestCoversCurrentExceptions(IntegrityReportService $reporter, LeaveOverlapService $leaveOverlaps, array $manifest): void
    {
        $report = $reporter->report();
        foreach ([
            'invalid_schedule_times',
            'orphaned_attendance_schedules',
            'orphaned_attendance_users',
            'orphaned_leave_users',
            'orphaned_attendance_events',
            'orphaned_student_courses',
            'orphaned_student_users',
            'orphaned_student_sections',
            'orphaned_courses',
            'orphaned_sections',
            'orphaned_instructor_users',
            'orphaned_instructor_departments',
            'orphaned_staff_users',
            'orphaned_schedules',
            'orphaned_event_targets',
            'orphaned_qr_credentials',
        ] as $category) {
            if ($report[$category] !== []) {
                throw new RuntimeException('Resolve '.$category.' before applying integrity constraints.');
            }
        }

        $placementIds = collect($manifest['placements'] ?? [])->pluck('student_id')->map(fn ($id) => (int) $id);
        $missingPlacements = collect($report['student_course_section_mismatches'])
            ->map(fn ($id) => (int) $id)
            ->diff($placementIds);
        if ($missingPlacements->isNotEmpty()) {
            throw new RuntimeException('Every student course/section mismatch requires an explicit placement decision: '.$missingPlacements->implode(', '));
        }

        $leaveDecisionKeys = collect($manifest['leave'] ?? [])
            ->map(fn (array $decision) => implode(',', collect($decision['ids'] ?? [])->sort()->values()->all()));
        foreach ($leaveOverlaps->groups() as $group) {
            $key = implode(',', collect($group->pluck('id')->all())->sort()->values()->all());
            if (! $leaveDecisionKeys->contains($key)) {
                throw new RuntimeException('Every leave overlap group requires an explicit resolution decision: '.$key);
            }
        }
    }

    private function reconcileAttendance(IntegrityReportService $reporter, IntegrityKeyService $keys, array $decisions): void
    {
        $byIds = collect($decisions)->keyBy(fn (array $decision) => implode(',', collect($decision['ids'] ?? [])->sort()->values()->all()));

        foreach ($reporter->duplicateAttendanceGroups() as $group) {
            $ids = $group->pluck('id')->all();
            $decision = $byIds->get(implode(',', collect($ids)->sort()->values()->all()));
            $canonical = $reporter->canonicalAttendance($group);

            if (! $decision || (int) ($decision['canonical_id'] ?? 0) !== (int) $canonical->getKey()) {
                throw new RuntimeException('Every duplicate attendance group requires the deterministic canonical decision: '.implode(',', $ids));
            }

            $superseded = collect($decision['superseded_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (array_diff($ids, [$canonical->getKey()], $superseded) !== [] || array_diff($superseded, array_diff($ids, [$canonical->getKey()])) !== []) {
                throw new RuntimeException('Attendance decision IDs do not match group '.$canonical->getKey().'.');
            }

            DB::transaction(function () use ($ids, $canonical, $keys, $superseded): void {
                $locked = AttendanceLog::query()->whereKey($ids)->lockForUpdate()->get()->keyBy('id');
                $canonicalRow = $locked->get($canonical->getKey());
                if (! $canonicalRow) {
                    throw new RuntimeException('Attendance canonical row no longer exists: '.$canonical->getKey());
                }

                foreach ($superseded as $id) {
                    $row = $locked->get($id);
                    $row?->forceFill([
                        'attendance_date' => $keys->attendanceDate($row->scan_time),
                        'record_state' => 'superseded',
                        'superseded_by_id' => $canonicalRow->getKey(),
                        'integrity_key' => null,
                    ])->saveQuietly();
                }

                $canonicalRow->forceFill([
                    'attendance_date' => $keys->attendanceDate($canonicalRow->scan_time),
                    'record_state' => 'canonical',
                    'superseded_by_id' => null,
                    'integrity_key' => $keys->forAttendance($canonicalRow),
                ])->saveQuietly();
            });
        }

        AttendanceLog::canonical()->orderBy('id')->chunkById(500, function (Collection $logs) use ($keys): void {
            foreach ($logs as $log) {
                $log->forceFill([
                    'attendance_date' => $keys->attendanceDate($log->scan_time),
                    'integrity_key' => $keys->forAttendance($log),
                ])->saveQuietly();
            }
        });
    }

    private function reconcileLeave(LeaveOverlapService $service, array $decisions, AuditLogger $audit): void
    {
        $decisions = collect($decisions)->keyBy(fn (array $decision) => implode(',', collect($decision['ids'] ?? [])->sort()->values()->all()));

        foreach ($service->groups() as $group) {
            $ids = $group->pluck('id')->all();
            $key = implode(',', collect($ids)->sort()->values()->all());
            $decision = $decisions->get($key);

            if (! $decision) {
                continue;
            }

            $keepId = (int) ($decision['keep_id'] ?? 0);
            $resolveIds = collect($decision['resolve_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (! in_array($keepId, $ids, true) || array_diff($ids, [$keepId], $resolveIds) !== [] || array_diff($resolveIds, array_diff($ids, [$keepId])) !== []) {
                throw new RuntimeException('Leave decision IDs do not match overlap group '.$key.'.');
            }

            DB::transaction(function () use ($group, $ids, $keepId, $resolveIds, $service, $audit): void {
                User::query()->whereKey($group->first()->user_id)->lockForUpdate()->firstOrFail();
                $rows = LeaveRequest::query()->whereKey($ids)->lockForUpdate()->get()->keyBy('id');
                if ($rows->count() !== count($ids) || $rows->contains(fn (LeaveRequest $row) => ! in_array($row->status, LeaveOverlapService::ACTIVE_STATUSES, true))) {
                    throw new RuntimeException('Leave overlap group changed while it was being reconciled; rerun the report.');
                }
                $groupId = $service->assignOverlapGroup($rows);
                $rows->get($keepId)?->forceFill(['overlap_group_id' => $groupId, 'overlap_state' => 'resolved'])->saveQuietly();

                foreach ($resolveIds as $id) {
                    $row = $rows->get($id);
                    if (! $row) {
                        continue;
                    }
                    $newStatus = $row->status === 'pending' ? 'rejected' : 'cancelled';
                    $row->forceFill([
                        'status' => $newStatus,
                        'overlap_group_id' => $groupId,
                        'overlap_state' => 'resolved',
                        'reviewed_at' => now(),
                        'review_notes' => trim(($row->review_notes ? $row->review_notes.PHP_EOL : '').'Resolved by reviewed integrity manifest; request #'.$keepId.' retained.'),
                    ])->saveQuietly();
                    $audit->record('leave.overlap_resolved', $row, [
                        'retained_request_id' => $keepId,
                        'new_status' => $newStatus,
                    ]);
                }
            });
        }
    }

    private function reconcilePlacements(array $decisions): void
    {
        foreach ($decisions as $decision) {
            DB::transaction(function () use ($decision): void {
                $student = Student::query()->lockForUpdate()->findOrFail((int) $decision['student_id']);
                $sectionId = $decision['section_id'] ?? null;
                if ($sectionId !== null && ! Section::query()->whereKey($sectionId)->where('course_id', $student->course_id)->exists()) {
                    throw new RuntimeException('Placement decision does not match the student course: '.$student->id);
                }
                $student->forceFill(['section_id' => $sectionId])->save();
            });
        }
    }

    private function reconcileSections(IntegrityReportService $reporter, array $decisions, AuditLogger $audit): void
    {
        $decisions = collect($decisions)->keyBy(fn (array $decision) => implode(',', collect($decision['ids'] ?? [])->sort()->values()->all()));
        foreach ($reporter->duplicateSectionGroups() as $group) {
            $ids = $group->pluck('id')->all();
            $decision = $decisions->get(implode(',', collect($ids)->sort()->values()->all()));
            if (! $decision || ! isset($decision['canonical_id'])) {
                throw new RuntimeException('Every duplicate section group requires a reviewed archive decision.');
            }
            $canonicalId = (int) $decision['canonical_id'];
            $archiveIds = collect($decision['archive_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
            if (! in_array($canonicalId, $ids, true) || array_diff($ids, [$canonicalId], $archiveIds) !== [] || array_diff($archiveIds, array_diff($ids, [$canonicalId])) !== []) {
                throw new RuntimeException('Section decision IDs do not match group '.$canonicalId.'.');
            }
            DB::transaction(function () use ($archiveIds, $audit): void {
                Section::query()->whereKey($archiveIds)->lockForUpdate()->update([
                    'archived_at' => now(),
                    'active_identity_key' => null,
                ]);
                Schedule::query()->whereIn('section_id', $archiveIds)->update(['active_identity_key' => null]);
                $audit->record('record.archived', null, ['record' => 'section', 'ids' => $archiveIds]);
            });
        }
    }

    private function reconcileSchedules(IntegrityReportService $reporter, array $decisions, AuditLogger $audit): void
    {
        $decisions = collect($decisions)->keyBy(fn (array $decision) => implode(',', collect($decision['ids'] ?? [])->sort()->values()->all()));
        foreach ($reporter->duplicateScheduleGroups() as $group) {
            $ids = $group->pluck('id')->all();
            $decision = $decisions->get(implode(',', collect($ids)->sort()->values()->all()));
            if (! $decision || ! isset($decision['canonical_id'])) {
                throw new RuntimeException('Every duplicate schedule group requires a reviewed archive decision.');
            }
            $canonicalId = (int) $decision['canonical_id'];
            $archiveIds = collect($decision['archive_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
            if (! in_array($canonicalId, $ids, true) || array_diff($ids, [$canonicalId], $archiveIds) !== [] || array_diff($archiveIds, array_diff($ids, [$canonicalId])) !== []) {
                throw new RuntimeException('Schedule decision IDs do not match group '.$canonicalId.'.');
            }
            DB::transaction(function () use ($archiveIds, $audit): void {
                Schedule::query()->whereKey($archiveIds)->lockForUpdate()->update([
                    'archived_at' => now(),
                    'active_identity_key' => null,
                ]);
                $audit->record('record.archived', null, ['record' => 'schedule', 'ids' => $archiveIds]);
            });
        }
    }

    private function backfillReferenceKeys(IntegrityKeyService $keys): void
    {
        Section::query()->orderBy('id')->chunkById(500, function (Collection $sections) use ($keys): void {
            foreach ($sections as $section) {
                $section->forceFill([
                    'active_identity_key' => $keys->sectionIsActive($section) ? $keys->sectionKey($section) : null,
                ])->saveQuietly();
            }
        });
        Schedule::query()->orderBy('id')->chunkById(500, function (Collection $schedules) use ($keys): void {
            foreach ($schedules as $schedule) {
                $schedule->forceFill([
                    'active_identity_key' => $keys->scheduleIsActive($schedule) ? $keys->scheduleKey($schedule) : null,
                ])->saveQuietly();
            }
        });
    }
}
