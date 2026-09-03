<?php

use Illuminate\Foundation\Inspiring;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:mark-student-absences')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::command('attendance:mark-school-event-absences')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(5);

Schedule::command('ops:scheduler-heartbeat')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(2);

Schedule::job(new RecordQueueHeartbeat)
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(2);

Schedule::command('reports:prune-exports')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping(15);
