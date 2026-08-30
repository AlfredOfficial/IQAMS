<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('roles')->select('role_name')->groupBy('role_name')->havingRaw('COUNT(*) > 1')->pluck('role_name');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Duplicate legacy roles must be resolved before migration: '.$duplicates->implode(', '));
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->string('name')->nullable()->after('role_name');
            $table->string('guard_name')->default('web')->after('name');
        });

        DB::table('roles')->update(['name' => DB::raw('role_name'), 'guard_name' => 'web']);

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('role_name', 'roles_role_name_unique');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->unique(['model_id', 'model_type'], 'model_has_roles_one_role_per_model');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        DB::table('users')->whereNotNull('role_id')->orderBy('id')->each(function ($user) {
            if (DB::table('roles')->where('id', $user->role_id)->exists()) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $user->role_id,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_role_name_unique');
            $table->dropUnique('roles_name_guard_name_unique');
            $table->dropColumn(['name', 'guard_name']);
        });
    }
};
