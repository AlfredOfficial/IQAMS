<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 100);
            $table->string('format', 10);
            $table->json('parameters');
            $table->string('status', 20)->index();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(['requested_by', 'created_at'], 'report_exports_requester_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
