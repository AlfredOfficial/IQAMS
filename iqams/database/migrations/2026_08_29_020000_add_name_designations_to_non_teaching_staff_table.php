<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->string('name_prefix')->nullable()->after('employee_no');
            $table->string('name_suffix')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->dropColumn(['name_prefix', 'name_suffix']);
        });
    }
};
