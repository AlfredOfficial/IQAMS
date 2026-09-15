<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['student', 'instructor', 'staff'])
            ->pluck('id', 'name');

        foreach ($roleIds as $role => $roleId) {
            $identifierColumn = match ($role) {
                'student' => 'student_no',
                default => 'employee_no',
            };
            $profileTable = match ($role) {
                'student' => 'students',
                'instructor' => 'instructors',
                default => 'non_teaching_staff',
            };

            DB::table('users')
                ->join('model_has_roles', function ($join) use ($roleId) {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User')
                        ->where('model_has_roles.role_id', '=', $roleId);
                })
                ->join($profileTable, $profileTable.'.user_id', '=', 'users.id')
                ->select('users.id', $profileTable.'.'.$identifierColumn)
                ->orderBy('users.id')
                ->get()
                ->each(function ($account) use ($role, $identifierColumn) {
                    $prefix = match ($role) {
                        'student' => 'Student@',
                        'instructor' => 'Instructor@',
                        default => 'Staff@',
                    };
                    $temporaryPassword = $prefix.$account->{$identifierColumn};

                    DB::table('users')->where('id', $account->id)->update([
                        'password' => Hash::make($temporaryPassword),
                        'must_change_password' => true,
                        'password_changed_at' => null,
                        'remember_token' => Str::random(60),
                        'session_version' => DB::raw('session_version + 1'),
                        'updated_at' => now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        // Password hashes cannot be safely restored after this migration.
    }
};
