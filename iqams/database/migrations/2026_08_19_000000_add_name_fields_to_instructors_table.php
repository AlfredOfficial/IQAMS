<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('name_prefix', 50)->nullable()->after('employee_no');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('professional_credentials')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn(['name_prefix', 'middle_name', 'professional_credentials']);
        });
    }
};
