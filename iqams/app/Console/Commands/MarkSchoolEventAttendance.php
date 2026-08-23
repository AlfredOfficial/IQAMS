<?php

namespace App\Console\Commands;

use App\Services\SchoolEventAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkSchoolEventAttendance extends Command
{
    protected $signature = 'attendance:mark-school-event-absences {--at=}';

    protected $description = 'Mark missing required school-event attendance';

    public function handle(SchoolEventAttendanceService $service): int
    {
        $at = $this->option('at') ? Carbon::parse($this->option('at'), config('app.timezone')) : null;
        $this->info($service->markDue($at).' event absence records created.');

        return self::SUCCESS;
    }
}
