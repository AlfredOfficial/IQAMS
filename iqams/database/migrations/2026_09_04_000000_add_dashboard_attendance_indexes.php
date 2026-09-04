<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These indexes were selected after EXPLAIN/timing against a temporary
     * 50,000-row attendance workload. The timestamp is first because the
     * dashboard always bounds its date window before applying the canonical
     * record filter.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->index(['updated_at', 'id'], 'attendance_logs_updated_id_index');
            $table->index(['scan_time', 'record_state', 'id'], 'attendance_logs_scan_record_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropIndex('attendance_logs_updated_id_index');
            $table->dropIndex('attendance_logs_scan_record_id_index');
        });
    }
};
