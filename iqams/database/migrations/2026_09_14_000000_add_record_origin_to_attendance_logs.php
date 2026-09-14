<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->string('record_origin', 20)->nullable()->after('scan_key')->index();
        });

        DB::table('attendance_logs')->whereNotNull('scanner_location')->update(['record_origin' => 'scanner']);
        DB::table('attendance_logs')->whereNull('record_origin')->where('status', 'absent')->whereNull('scanner_location')->update(['record_origin' => 'system']);
        DB::table('attendance_logs')->whereNull('record_origin')->update(['record_origin' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropIndex(['record_origin']);
            $table->dropColumn('record_origin');
        });
    }
};
