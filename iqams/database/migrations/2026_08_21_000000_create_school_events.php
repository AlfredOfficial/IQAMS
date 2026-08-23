<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('attendance_mode', ['cancelled', 'event_attendance', 'unchanged']);
            $table->enum('target_scope', ['school', 'sections', 'schedules']);
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('school_event_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['school_event_id', 'section_id']);
            $table->unique(['school_event_id', 'schedule_id']);
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('school_event_id')->nullable()->after('schedule_id')
                ->constrained()->restrictOnDelete();
            $table->index(['school_event_id', 'scan_time']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_event_id');
        });
        Schema::dropIfExists('school_event_targets');
        Schema::dropIfExists('school_events');
    }
};
