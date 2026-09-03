<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\QrCredentialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IssueMissingQrCredentials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public array $filters, public int $administratorId) {}

    public function handle(QrCredentialService $credentials, AuditLogger $audit): void
    {
        $administrator = User::findOrFail($this->administratorId);
        $issued = 0;
        $skipped = 0;
        $failed = 0;

        $this->users()->chunkById(100, function ($users) use ($credentials, $administrator, &$issued, &$skipped, &$failed): void {
            foreach ($users as $user) {
                try {
                    if ($credentials->issueIfMissing($user, $administrator)) {
                        $issued++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    report($exception);
                }
            }
        });

        $audit->record('qr.batch_completed', null, [
            'issued' => $issued,
            'skipped' => $skipped,
            'failed' => $failed,
            'filters' => $this->filters,
        ], $administrator);
    }

    private function users()
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['student', 'instructor', 'staff'])->where('guard_name', 'web'))
            ->when($this->filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $role)->where('guard_name', 'web')))
            ->when($this->filters['department_id'] ?? null, function ($query, $departmentId): void {
                $query->where(function ($query) use ($departmentId): void {
                    $query->whereHas('instructor', fn ($profile) => $profile->where('department_id', $departmentId))
                        ->orWhereHas('student.course', fn ($course) => $course->where('department_id', $departmentId));
                });
            })
            ->when($this->filters['office_unit_id'] ?? null, fn ($query, $officeUnitId) => $query->whereHas('nonTeachingStaff', fn ($profile) => $profile->where('office_unit_id', $officeUnitId)))
            ->when($this->filters['course_id'] ?? null, fn ($query, $courseId) => $query->whereHas('student', fn ($profile) => $profile->where('course_id', $courseId)))
            ->when($this->filters['section_id'] ?? null, fn ($query, $sectionId) => $query->whereHas('student', fn ($profile) => $profile->where('section_id', $sectionId)))
            ->orderBy('id');
    }
}
