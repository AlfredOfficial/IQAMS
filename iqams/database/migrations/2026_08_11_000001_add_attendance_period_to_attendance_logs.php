<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('attendance_period', 30)->nullable()->after('attendance_type');
            $table->index(['user_id', 'attendance_period', 'scan_time'], 'attendance_logs_user_period_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_logs_user_period_time_index');
            $table->dropColumn('attendance_period');
        });
    }
};
