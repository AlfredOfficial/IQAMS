<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardReferenceCache
{
    public const KEY = 'admin-dashboard.references.v1';

    public static function data(): array
    {
        return Cache::remember(self::KEY, now()->addMinutes(5), fn () => [
            'totals' => [
                'student' => User::whereHas('roles', fn ($query) => $query->where('name', 'student')->where('guard_name', 'web'))->count(),
                'instructor' => User::whereHas('roles', fn ($query) => $query->where('name', 'instructor')->where('guard_name', 'web'))->count(),
                'staff' => User::whereHas('roles', fn ($query) => $query->where('name', 'staff')->where('guard_name', 'web'))->count(),
            ],
            'filters' => [
                'departments' => Department::active()->orderBy('department_name')->pluck('department_name')->values()->all(),
                'sections' => Section::active()->orderBy('section_name')->pluck('section_name')->values()->all(),
                'subjects' => Subject::active()->orderBy('subject_name')->pluck('subject_name')->values()->all(),
            ],
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
