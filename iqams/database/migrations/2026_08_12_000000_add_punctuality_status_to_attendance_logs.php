<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('punctuality_status', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', fn (Blueprint $table) => $table->dropColumn('punctuality_status'));
    }
};
