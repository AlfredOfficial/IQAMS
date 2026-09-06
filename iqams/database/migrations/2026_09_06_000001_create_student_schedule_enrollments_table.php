<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL applies CREATE and subsequent index ALTER statements separately.
        // If an earlier run failed on an index name, complete that empty partial table.
        if (Schema::hasTable('student_schedule_enrollments')) {
            Schema::table('student_schedule_enrollments', function (Blueprint $table) {
                $table->unique(['student_id', 'recurring_schedule_group_id'], 'student_schedule_enrollment_unique');
                $table->index('recurring_schedule_group_id', 'student_schedule_group_index');
            });

            return;
        }

        Schema::create('student_schedule_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->uuid('recurring_schedule_group_id');
            $table->timestamps();

            $table->unique(['student_id', 'recurring_schedule_group_id'], 'student_schedule_enrollment_unique');
            $table->index('recurring_schedule_group_id', 'student_schedule_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_schedule_enrollments');
    }
};
