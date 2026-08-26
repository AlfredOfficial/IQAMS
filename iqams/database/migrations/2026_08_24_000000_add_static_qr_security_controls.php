<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->text('encrypted_code');
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('scanner_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_scan_previews', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scanner_terminal_id')->constrained()->cascadeOnDelete();
            $table->text('encrypted_qr_value');
            $table->boolean('is_legacy')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_scan_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scanner_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 40)->index();
            $table->string('failure_category', 60)->nullable()->index();
            $table->string('credential_type', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('security_flags', function (Blueprint $table) {
            $table->id();
            $table->string('severity', 20)->default('medium')->index();
            $table->string('category', 60)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scanner_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_scan_audit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('deduplication_key')->index();
            $table->text('evidence');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('detected_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_flags');
        Schema::dropIfExists('attendance_scan_audits');
        Schema::dropIfExists('attendance_scan_previews');
        Schema::dropIfExists('scanner_terminals');
        Schema::dropIfExists('qr_credentials');
    }
};
