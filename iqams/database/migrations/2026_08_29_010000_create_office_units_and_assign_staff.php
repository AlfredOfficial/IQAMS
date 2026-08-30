<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('office_units')->insert([
            ['code' => 'REG', 'name' => 'Registrar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ACC', 'name' => 'Accounting', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LIB', 'name' => 'Library', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MIS', 'name' => 'MIS/IT', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SAO', 'name' => 'Student Affairs', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->foreignId('office_unit_id')->nullable()->after('department_id')
                ->constrained('office_units')->restrictOnDelete();
        });

        $mappings = [
            'REG' => ['REG', 'REGISTRAR'],
            'ACC' => ['ACC', 'ACCOUNTING'],
            'HR' => ['HR', 'HUMAN RESOURCES'],
            'LIB' => ['LIB', 'LIBRARY'],
            'MIS' => ['MIS', 'MIS/IT', 'IT OFFICE', 'MANAGEMENT INFORMATION SYSTEMS'],
            'SAO' => ['SAO', 'STUDENT AFFAIRS'],
        ];

        foreach ($mappings as $officeCode => $legacyValues) {
            $officeId = DB::table('office_units')->where('code', $officeCode)->value('id');
            $departmentIds = DB::table('departments')
                ->whereIn(DB::raw('UPPER(department_code)'), $legacyValues)
                ->orWhereIn(DB::raw('UPPER(department_name)'), $legacyValues)
                ->pluck('id');

            if ($departmentIds->isNotEmpty()) {
                DB::table('non_teaching_staff')->whereIn('department_id', $departmentIds)
                    ->whereNull('office_unit_id')->update(['office_unit_id' => $officeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('non_teaching_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_unit_id');
        });
        Schema::dropIfExists('office_units');
    }
};
