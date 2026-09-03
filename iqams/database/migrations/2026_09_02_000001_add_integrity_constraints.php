<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertStagingBackfillComplete();

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unique('integrity_key', 'attendance_logs_integrity_key_unique');
            $table->foreign('superseded_by_id', 'attendance_logs_superseded_by_foreign')
                ->references('id')->on('attendance_logs')->restrictOnDelete();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique('active_identity_key', 'sections_active_identity_unique');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unique('active_identity_key', 'schedules_active_identity_unique');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['id', 'course_id'], 'sections_id_course_id_unique');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreign(['section_id', 'course_id'], 'students_section_course_foreign')
                ->references(['id', 'course_id'])->on('sections')->restrictOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_valid_time_check CHECK (start_time <> end_time)');
        }

        $this->restrictHistoricalForeignKeys($driver);
    }

    private function assertStagingBackfillComplete(): void
    {
        $attendanceNeedsBackfill = DB::table('attendance_logs')
            ->where(function ($query) {
                $query->where('record_state', 'canonical')->orWhereNull('record_state');
            })
            ->whereNull('integrity_key')
            ->where(function ($query) {
                $query->whereNotNull('school_event_id')
                    ->orWhereNotNull('schedule_id')
                    ->orWhereNotNull('attendance_period');
            })
            ->exists();
        $sectionsNeedBackfill = DB::table('sections')->whereNull('archived_at')->whereNull('active_identity_key')->exists();
        $schedulesNeedBackfill = DB::table('schedules')->whereNull('archived_at')->whereNull('active_identity_key')->exists();

        if ($attendanceNeedsBackfill || $sectionsNeedBackfill || $schedulesNeedBackfill) {
            throw new RuntimeException('Run integrity:reconcile --apply with a reviewed manifest before enabling integrity constraints.');
        }
    }

    private function restrictHistoricalForeignKeys(string $driver): void
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // These relationships must not cascade-delete attendance, leave, or
        // schedule history. The exact constraint names are stable because
        // Laravel derives them from the column and table names.
        $foreignKeys = [
            ['attendance_logs', 'attendance_logs_user_id_foreign', 'user_id', 'users'],
            ['attendance_logs', 'attendance_logs_schedule_id_foreign', 'schedule_id', 'schedules'],
            ['leave_requests', 'leave_requests_user_id_foreign', 'user_id', 'users'],
            ['courses', 'courses_department_id_foreign', 'department_id', 'departments'],
            ['schedules', 'schedules_subject_id_foreign', 'subject_id', 'subjects'],
            ['schedules', 'schedules_instructor_id_foreign', 'instructor_id', 'instructors'],
            ['schedules', 'schedules_section_id_foreign', 'section_id', 'sections'],
            ['instructors', 'instructors_user_id_foreign', 'user_id', 'users'],
            ['instructors', 'instructors_department_id_foreign', 'department_id', 'departments'],
            ['students', 'students_user_id_foreign', 'user_id', 'users'],
            ['students', 'students_course_id_foreign', 'course_id', 'courses'],
            ['students', 'students_section_id_foreign', 'section_id', 'sections'],
            ['non_teaching_staff', 'non_teaching_staff_user_id_foreign', 'user_id', 'users'],
            ['sections', 'sections_course_id_foreign', 'course_id', 'courses'],
            ['school_event_targets', 'school_event_targets_school_event_id_foreign', 'school_event_id', 'school_events'],
            ['school_event_targets', 'school_event_targets_section_id_foreign', 'section_id', 'sections'],
            ['school_event_targets', 'school_event_targets_schedule_id_foreign', 'schedule_id', 'schedules'],
            ['qr_credentials', 'qr_credentials_user_id_foreign', 'user_id', 'users'],
        ];

        foreach ($foreignKeys as [$table, $constraint, $column, $referencedTable]) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        // Intentionally empty. Constraint rollback must be an approved
        // forward migration or a database restore, never a destructive down.
    }
};
