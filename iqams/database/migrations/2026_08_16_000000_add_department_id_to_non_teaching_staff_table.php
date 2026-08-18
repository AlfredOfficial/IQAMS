<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
