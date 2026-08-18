<?php

namespace App\Console\Commands;

use App\Services\StudentAbsenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkStudentAbsences extends Command
{
    protected $signature = 'attendance:mark-student-absences {--at= : Date/time override for testing}';

    protected $description = 'Create absent records for students who missed a scheduled subject present window';

    public function handle(StudentAbsenceService $absences): int
    {
        $at = $this->option('at')
            ? Carbon::parse($this->option('at'), config('app.timezone'))
            : null;

        $count = $absences->markDue($at);
        $this->info($count === 1
            ? '1 attendance record marked as Absent.'
            : "{$count} attendance records marked as Absent.");

        return self::SUCCESS;
    }
}
