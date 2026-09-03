<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PersonnelAttendanceReportService
{
    public function getDailyReport(Carbon $date, array $filters = []): array
    {
        $personnel = $this->personnel($filters);
        $logs = collect();
        AttendanceLog::canonical()
            ->whereIn('user_id', $personnel->pluck('user_id'))
            ->whereNull('schedule_id')
            ->whereNull('school_event_id')
            ->whereBetween('scan_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->whereIn('attendance_period', PersonnelAttendanceSummary::PERIODS)
            ->orderBy('scan_time')
            ->chunkById(500, function (Collection $chunk) use (&$logs): void {
                $logs = $logs->concat($chunk);
            }, 'attendance_logs.id', 'id');
        $logs = $logs->groupBy('user_id');

        $rows = $personnel->map(function (array $person) use ($logs): array {
            $personLogs = $logs->get($person['user_id'], collect())->groupBy('attendance_period');

            return $person + [
                'morning_time_in' => $this->time($personLogs->get('morning_in'), false),
                'morning_time_out' => $this->time($personLogs->get('lunch_out'), true),
                'afternoon_time_in' => $this->time($personLogs->get('afternoon_in'), false),
                'afternoon_time_out' => $this->time($personLogs->get('final_out'), true),
            ];
        })->values();

        return ['date' => $date->copy(), 'rows' => $rows, 'filters' => $filters];
    }

    public function filterOptions(): array
    {
        return [
            'departments' => Department::active()->orderBy('department_name')->get(['id', 'department_code', 'department_name']),
            'officeUnits' => OfficeUnit::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'personnel' => $this->personnel([])->map(fn (array $person) => [
                'user_id' => $person['user_id'],
                'name' => $person['name'],
                'personnel_type' => $person['personnel_type'],
            ]),
        ];
    }

    private function personnel(array $filters): Collection
    {
        $type = $filters['personnel_type'] ?? null;
        $departmentId = $filters['department_id'] ?? null;
        $officeUnitId = $filters['office_unit_id'] ?? null;
        $personnelId = $filters['personnel_id'] ?? null;
        $people = collect();

        if ((! $type || $type === 'instructor') && ! $officeUnitId) {
            $instructors = collect();
            Instructor::query()
                ->with('user:id,status')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->when($personnelId, fn ($query) => $query->where('user_id', $personnelId))
                ->chunkById(500, function (Collection $chunk) use (&$instructors): void {
                    $instructors = $instructors->concat($chunk);
                });

            $people = $people->concat($instructors->map(fn (Instructor $instructor) => [
                'name' => $instructor->fullName(),
                'personnel_type' => 'instructor',
                'personnel_id' => $instructor->employee_no,
                'user_id' => $instructor->user_id,
            ]));
        }

        if ((! $type || $type === 'staff') && ! $departmentId) {
            $staff = collect();
            NonTeachingStaff::query()
                ->with('user:id,status')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->when($officeUnitId, fn ($query) => $query->where('office_unit_id', $officeUnitId))
                ->when($personnelId, fn ($query) => $query->where('user_id', $personnelId))
                ->chunkById(500, function (Collection $chunk) use (&$staff): void {
                    $staff = $staff->concat($chunk);
                });

            $people = $people->concat($staff->map(fn (NonTeachingStaff $staffMember) => [
                'name' => $staffMember->fullName(),
                'personnel_type' => 'staff',
                'personnel_id' => $staffMember->employee_no,
                'user_id' => $staffMember->user_id,
            ]));
        }

        return $people->sortBy(fn (array $person) => mb_strtolower($person['name']))->values();
    }

    private function time(?Collection $logs, bool $latest): string
    {
        if (! $logs || $logs->isEmpty()) {
            return '';
        }

        $log = $latest ? $logs->sortByDesc('scan_time')->first() : $logs->sortBy('scan_time')->first();

        return $log->scan_time->copy()->timezone(config('app.timezone'))->format('g:i A');
    }
}
