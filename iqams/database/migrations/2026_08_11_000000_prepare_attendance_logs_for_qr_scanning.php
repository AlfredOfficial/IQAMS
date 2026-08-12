<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->change();
            $table->string('scan_key')->nullable()->unique()->after('scan_time');
            $table->index(['user_id', 'scan_time'], 'attendance_logs_user_scan_time_index');
            $table->index(['schedule_id', 'scan_time'], 'attendance_logs_schedule_scan_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_logs_user_scan_time_index');
            $table->dropIndex('attendance_logs_schedule_scan_time_index');
            $table->dropUnique(['scan_key']);
            $table->dropColumn('scan_key');
        });
    }
};
