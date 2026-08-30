<?php

namespace App\Console\Commands;

use App\Models\NonTeachingStaff;
use Illuminate\Console\Command;

class AuditStaffOfficeUnits extends Command
{
    protected $signature = 'staff:office-units-audit';

    protected $description = 'Read-only report of unresolved or inactive non-teaching office assignments';

    public function handle(): int
    {
        $issues = NonTeachingStaff::with(['user', 'department', 'officeUnit'])
            ->where(function ($query) {
                $query->whereNull('office_unit_id')
                    ->orWhereHas('officeUnit', fn ($office) => $office->where('is_active', false));
            })->orderBy('id')->get();

        if ($issues->isEmpty()) {
            $this->info('All non-teaching staff have active office/unit assignments. No data was changed.');

            return self::SUCCESS;
        }

        $this->table(
            ['Staff ID', 'Employee ID', 'Name', 'Legacy department', 'Office/unit', 'Issue'],
            $issues->map(fn (NonTeachingStaff $staff) => [
                $staff->id,
                $staff->employee_no,
                $staff->fullName(),
                $staff->department?->department_name ?? 'None',
                $staff->officeUnit?->name ?? 'None',
                $staff->office_unit_id ? 'Office/unit is inactive' : 'Office/unit is not assigned',
            ]),
        );
        $this->error("{$issues->count()} staff assignment issue(s) found. No data was changed.");

        return self::FAILURE;
    }
}
