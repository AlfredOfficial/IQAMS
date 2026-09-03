<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->date('attendance_date')->nullable()->after('scan_time');
            $table->string('integrity_key', 64)->nullable()->after('scan_key');
            $table->string('record_state', 20)->default('canonical')->after('integrity_key');
            $table->unsignedBigInteger('superseded_by_id')->nullable()->after('record_state');
            $table->index(['user_id', 'attendance_date'], 'attendance_logs_user_date_index');
            $table->index(['schedule_id', 'attendance_date'], 'attendance_logs_schedule_date_index');
            $table->index(['school_event_id', 'attendance_date'], 'attendance_logs_event_date_index');
            $table->index(['user_id', 'attendance_period', 'attendance_date'], 'attendance_logs_user_period_date_index');
            $table->index('superseded_by_id', 'attendance_logs_superseded_by_index');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->uuid('overlap_group_id')->nullable()->after('review_notes');
            $table->string('overlap_state', 20)->default('clear')->after('overlap_group_id');
            $table->index(['overlap_group_id', 'overlap_state'], 'leave_requests_overlap_group_index');
        });

        foreach (['departments', 'courses', 'sections', 'subjects', 'schedules'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->index();
            });
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->string('active_identity_key', 64)->nullable();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->string('active_identity_key', 64)->nullable();
            $table->index(['section_id', 'day', 'start_time'], 'schedules_section_day_start_index');
            $table->index(['instructor_id', 'day', 'start_time'], 'schedules_instructor_day_start_index');
        });
    }

    public function down(): void
    {
        // Production rollback is performed by restoring the previous release
        // and, when necessary, the approved database backup. Do not remove
        // integrity or archive data from a live database through a rollback.
    }
};
