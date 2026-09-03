<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->index(
                ['schedule_id', 'attendance_type', 'user_id', 'scan_time'],
                'attendance_logs_schedule_type_user_time_index',
            );
            $table->index(
                ['school_event_id', 'attendance_type', 'user_id', 'scan_time'],
                'attendance_logs_event_type_user_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_logs_schedule_type_user_time_index');
            $table->dropIndex('attendance_logs_event_type_user_index');
        });
    }
};
