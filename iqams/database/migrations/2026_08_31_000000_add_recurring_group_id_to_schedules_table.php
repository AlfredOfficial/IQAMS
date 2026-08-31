<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->uuid('recurring_schedule_group_id')->nullable()->after('id')->index();
        });

        DB::table('schedules')
            ->select(['id', 'subject_id', 'section_id', 'instructor_id', 'start_time', 'end_time', 'room'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($schedule) => json_encode([
                $schedule->subject_id,
                $schedule->section_id,
                $schedule->instructor_id,
                $schedule->start_time,
                $schedule->end_time,
                $schedule->room,
            ]))
            ->each(function ($schedules) {
                DB::table('schedules')
                    ->whereIn('id', $schedules->pluck('id'))
                    ->update(['recurring_schedule_group_id' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('recurring_schedule_group_id');
        });
    }
};
