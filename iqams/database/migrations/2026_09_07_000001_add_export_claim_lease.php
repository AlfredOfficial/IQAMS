<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->uuid('claim_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropIndex(['lease_expires_at']);
            $table->dropColumn(['claim_token', 'lease_expires_at']);
        });
    }
};
