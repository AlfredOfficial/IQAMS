<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DailyPersonnelAttendanceReportFilters
{
    /**
     * @return array{0: Carbon, 1: array<string, mixed>}
     */
    public function validate(Request $request): array
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'personnel_type' => ['nullable', Rule::in(['instructor', 'staff'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'office_unit_id' => ['nullable', 'integer', 'exists:office_units,id'],
            'personnel_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->validateMutuallyExclusiveFilters($filters);

        if (! empty($filters['personnel_id'])) {
            $isPersonnel = User::whereKey($filters['personnel_id'])
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereHas('instructor')->orWhereHas('nonTeachingStaff'))
                ->exists();
            if (! $isPersonnel) {
                throw ValidationException::withMessages(['personnel_id' => 'Select an active instructor or non-teaching staff member.']);
            }
        }

        $date = Carbon::createFromFormat(
            'Y-m-d',
            $filters['date'] ?? today()->toDateString(),
            config('app.timezone'),
        )->startOfDay();
        unset($filters['date']);

        return [$date, array_filter($filters, fn ($value) => $value !== null && $value !== '')];
    }

    private function validateMutuallyExclusiveFilters(array $filters): void
    {
        if (($filters['personnel_type'] ?? null) === 'staff' && ! empty($filters['department_id'])) {
            throw ValidationException::withMessages(['department_id' => 'Department filters only apply to instructors.']);
        }
        if (($filters['personnel_type'] ?? null) === 'instructor' && ! empty($filters['office_unit_id'])) {
            throw ValidationException::withMessages(['office_unit_id' => 'Office/unit filters only apply to non-teaching staff.']);
        }
        if (! empty($filters['department_id']) && ! empty($filters['office_unit_id'])) {
            throw ValidationException::withMessages(['office_unit_id' => 'Choose either a department or an office/unit filter.']);
        }
    }
}
