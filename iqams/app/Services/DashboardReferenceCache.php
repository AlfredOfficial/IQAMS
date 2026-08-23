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
                'student' => User::whereHas('role', fn ($query) => $query->where('role_name', 'student'))->count(),
                'instructor' => User::whereHas('role', fn ($query) => $query->where('role_name', 'instructor'))->count(),
                'staff' => User::whereHas('role', fn ($query) => $query->where('role_name', 'staff'))->count(),
            ],
            'filters' => [
                'departments' => Department::orderBy('department_name')->pluck('department_name')->values()->all(),
                'sections' => Section::orderBy('section_name')->pluck('section_name')->values()->all(),
                'subjects' => Subject::orderBy('subject_name')->pluck('subject_name')->values()->all(),
            ],
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
