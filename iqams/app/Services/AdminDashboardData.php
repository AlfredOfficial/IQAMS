<?php

namespace App\Services;

use App\Models\AttendanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardData
{
    private const SCAN_LIMIT = 250;

    public function build(?Carbon $cursor = null, bool $includeFilters = false): array
    {
        $generatedAt = now();
        $nextCursor = $generatedAt->copy()->subSecond()->startOfSecond();
        $today = $generatedAt->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $weekStart = $generatedAt->copy()->startOfWeek()->startOfDay();
        $references = DashboardReferenceCache::data();

        $scanQuery = $this->scanQuery()
            ->when($cursor, fn (Builder $query) => $query
                ->where('attendance_logs.updated_at', '>=', $cursor)
                ->where('attendance_logs.updated_at', '<=', $generatedAt));
        $scans = $scanQuery
            ->latest($cursor ? 'attendance_logs.updated_at' : 'attendance_logs.scan_time')
            ->limit(self::SCAN_LIMIT)
            ->get();

        $roleMetrics = $this->roleMetrics($today, $tomorrow);
        $statusMetrics = $this->statusMetrics($today, $tomorrow);
        $personnelRows = $this->personnelRows($today, $tomorrow);
        [$incompleteUsers, $missingTimeout, $incompleteByRole] = $this->personnelExceptions($personnelRows, $generatedAt->hour);

        $stats = [
            'total_scanned' => $roleMetrics->sum('users'),
            'students' => (int) ($roleMetrics->get('student')['users'] ?? 0),
            'instructors' => (int) ($roleMetrics->get('instructor')['users'] ?? 0),
            'staff' => (int) ($roleMetrics->get('staff')['users'] ?? 0),
            'present' => $this->currentlyPresentCount($today, $tomorrow),
            'late' => (int) ($statusMetrics->get('late') ?? 0),
            'missing_timeout' => $missingTimeout,
            'incomplete' => $incompleteUsers,
        ];

        $payload = [
            'generated_at' => $generatedAt->toIso8601String(),
            'cursor' => $nextCursor->toIso8601String(),
            'stats' => $stats,
            'scans' => $scans->map(fn (AttendanceLog $log) => $this->formatLog($log))->values()->all(),
            'charts' => [
                'hourly' => $this->hourly($today, $tomorrow),
                'roles' => [
                    ['label' => 'Students', 'value' => (int) ($roleMetrics->get('student')['scans'] ?? 0)],
                    ['label' => 'Teaching', 'value' => (int) ($roleMetrics->get('instructor')['scans'] ?? 0)],
                    ['label' => 'Non-teaching', 'value' => (int) ($roleMetrics->get('staff')['scans'] ?? 0)],
                ],
                'statuses' => collect(['present', 'late', 'incomplete', 'missing'])->map(fn ($status) => [
                    'label' => ucfirst($status),
                    'value' => $status === 'incomplete' ? $incompleteUsers : ($status === 'missing' ? $missingTimeout : (int) ($statusMetrics->get($status) ?? 0)),
                ])->all(),
                'departments' => $this->departments($today, $tomorrow),
                'subjects' => $this->subjects($today, $tomorrow),
                'weekly' => $this->weekly($weekStart, $generatedAt->copy()->endOfDay()),
            ],
            'overview' => [
                $this->overviewRow('Students', 'student', $roleMetrics, $references['totals']['student'], false),
                $this->overviewRow('Teaching Personnel', 'instructor', $roleMetrics, $references['totals']['instructor'], true, $incompleteByRole['instructor']),
                $this->overviewRow('Non-teaching Personnel', 'staff', $roleMetrics, $references['totals']['staff'], true, $incompleteByRole['staff']),
            ],
        ];

        if ($includeFilters) {
            $payload['filters'] = $references['filters'];
        }

        return $payload;
    }

    private function scanQuery(): Builder
    {
        return AttendanceLog::with([
            'user.role', 'user.student.section.course', 'user.student.course',
            'user.instructor.department', 'user.nonTeachingStaff',
            'schedule.subject', 'schedule.section.course', 'schedule.instructor.user',
        ]);
    }

    private function roleMetrics(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')
            ->join('users', 'users.id', '=', 'attendance_logs.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('attendance_logs.scan_time', '>=', $from)
            ->where('attendance_logs.scan_time', '<', $to)
            ->groupBy('roles.role_name')
            ->selectRaw('roles.role_name, COUNT(*) as scans, COUNT(DISTINCT attendance_logs.user_id) as users, SUM(CASE WHEN attendance_logs.status = ? THEN 1 ELSE 0 END) as present, SUM(CASE WHEN attendance_logs.status = ? THEN 1 ELSE 0 END) as late', ['present', 'late'])
            ->get()->mapWithKeys(fn ($row) => [$row->role_name => [
                'scans' => (int) $row->scans, 'users' => (int) $row->users,
                'present' => (int) $row->present, 'late' => (int) $row->late,
            ]]);
    }

    private function statusMetrics(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')->where('scan_time', '>=', $from)->where('scan_time', '<', $to)
            ->groupBy('status')->selectRaw('status, COUNT(*) as aggregate')->pluck('aggregate', 'status');
    }

    private function personnelRows(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')->join('users', 'users.id', '=', 'attendance_logs.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')->whereIn('roles.role_name', ['instructor', 'staff'])
            ->where('attendance_logs.scan_time', '>=', $from)->where('attendance_logs.scan_time', '<', $to)
            ->orderBy('attendance_logs.scan_time')->get(['attendance_logs.user_id', 'attendance_logs.attendance_type', 'attendance_logs.scan_time', 'roles.role_name']);
    }

    private function personnelExceptions(Collection $rows, int $hour): array
    {
        $expected = $hour < 12 ? 1 : ($hour < 13 ? 2 : ($hour < 17 ? 3 : 4));
        $byUser = $rows->groupBy('user_id');
        $incomplete = $byUser->filter(fn ($logs) => $logs->count() < $expected)->count();
        $missing = in_array($expected, [2, 4], true)
            ? $byUser->filter(fn ($logs) => $logs->last()?->attendance_type === 'time_in')->count()
            : 0;

        $byRole = collect(['instructor', 'staff'])->mapWithKeys(fn ($role) => [$role => $byUser
            ->filter(fn ($logs) => $logs->first()?->role_name === $role && $logs->count() < $expected)
            ->count()])->all();

        return [$incomplete, $missing, $byRole];
    }

    private function currentlyPresentCount(Carbon $from, Carbon $to): int
    {
        $latest = DB::table('attendance_logs')->where('scan_time', '>=', $from)->where('scan_time', '<', $to)
            ->selectRaw('user_id, MAX(scan_time) as latest_scan')->groupBy('user_id');

        return DB::table('attendance_logs as logs')->joinSub($latest, 'latest', fn ($join) => $join
            ->on('logs.user_id', '=', 'latest.user_id')->on('logs.scan_time', '=', 'latest.latest_scan'))
            ->where('logs.attendance_type', 'time_in')->distinct()->count('logs.user_id');
    }

    private function hourly(Carbon $from, Carbon $to): array
    {
        $expression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', scan_time) AS INTEGER)"
            : 'HOUR(scan_time)';
        $counts = DB::table('attendance_logs')->where('scan_time', '>=', $from)->where('scan_time', '<', $to)
            ->selectRaw("{$expression} as bucket, COUNT(*) as aggregate")->groupBy('bucket')->pluck('aggregate', 'bucket');

        return collect(range(6, 20))->map(fn ($hour) => [
            'label' => Carbon::createFromTime($hour)->format('g A'), 'value' => (int) ($counts->get($hour) ?? 0),
        ])->all();
    }

    private function weekly(Carbon $from, Carbon $to): array
    {
        $expression = DB::getDriverName() === 'sqlite' ? "date(scan_time)" : 'DATE(scan_time)';
        $counts = DB::table('attendance_logs')->whereBetween('scan_time', [$from, $to])
            ->selectRaw("{$expression} as bucket, COUNT(*) as aggregate")->groupBy('bucket')->pluck('aggregate', 'bucket');

        return collect(range(0, 6))->map(function ($offset) use ($from, $counts) {
            $day = $from->copy()->addDays($offset);
            return ['label' => $day->format('D'), 'value' => (int) ($counts->get($day->toDateString()) ?? 0)];
        })->all();
    }

    private function departments(Carbon $from, Carbon $to): array
    {
        return DB::table('attendance_logs')->join('users', 'users.id', '=', 'attendance_logs.user_id')
            ->join('instructors', 'instructors.user_id', '=', 'users.id')->leftJoin('departments', 'departments.id', '=', 'instructors.department_id')
            ->where('attendance_logs.scan_time', '>=', $from)->where('attendance_logs.scan_time', '<', $to)
            ->groupBy('departments.department_code')->orderByDesc('aggregate')->limit(8)
            ->selectRaw("COALESCE(departments.department_code, 'Unassigned') as label, COUNT(*) as aggregate")
            ->get()->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->aggregate])->all();
    }

    private function subjects(Carbon $from, Carbon $to): array
    {
        return DB::table('attendance_logs')->join('schedules', 'schedules.id', '=', 'attendance_logs.schedule_id')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->where('attendance_logs.scan_time', '>=', $from)->where('attendance_logs.scan_time', '<', $to)
            ->groupBy('subjects.subject_code')->orderByDesc('aggregate')->limit(8)
            ->selectRaw('subjects.subject_code as label, COUNT(*) as aggregate')->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->aggregate])->all();
    }

    private function overviewRow(string $label, string $role, Collection $metrics, int $total, bool $fourScan, int $incomplete = 0): array
    {
        $row = $metrics->get($role, ['users' => 0, 'present' => 0, 'late' => 0]);
        $scanned = (int) $row['users'];
        $primary = $fourScan ? max(0, $scanned - $incomplete) : (int) $row['present'];
        $secondary = $fourScan ? min($scanned, $incomplete) : (int) $row['late'];

        return [
            'label' => $label, 'scanned' => $scanned, 'total' => $total,
            'primary_label' => $fourScan ? 'Complete' : 'Present', 'primary' => $primary,
            'secondary_label' => $fourScan ? 'Incomplete' : 'Late', 'secondary' => $secondary,
            'percentage' => $total > 0 ? min(100, (int) round(($scanned / $total) * 100)) : 0,
        ];
    }

    private function formatLog(AttendanceLog $log): array
    {
        $role = strtolower($log->user?->role?->role_name ?? 'unknown');
        $student = $log->user?->student;
        $instructor = $log->user?->instructor;
        $staff = $log->user?->nonTeachingStaff;
        $section = $student?->section ?? $log->schedule?->section;
        $department = $instructor?->department?->department_name;

        return [
            'id' => $log->id, 'user_id' => $log->user_id,
            'identifier' => $student?->student_no ?? $instructor?->employee_no ?? $staff?->employee_no ?? $log->user?->username ?? '—',
            'name' => $log->user?->name ?? 'Unknown user',
            'initials' => collect(explode(' ', $log->user?->name ?? '?'))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'avatar' => $log->user?->avatar_url, 'role_key' => $role,
            'role' => match ($role) { 'instructor' => 'Teaching Personnel', 'staff' => 'Non-teaching Personnel', 'student' => 'Student', default => ucfirst($role) },
            'section' => $section?->section_name, 'course' => $student?->course?->course_code ?? $section?->course?->course_code,
            'department' => $department ?? ($role === 'staff' ? 'Administration' : null),
            'group' => $role === 'student' ? ($section?->section_name ?? 'Unassigned') : ($department ?? ($role === 'staff' ? 'Administration' : 'Unassigned')),
            'subject' => $log->schedule?->subject?->subject_name, 'subject_code' => $log->schedule?->subject?->subject_code,
            'instructor' => $log->schedule?->instructor?->user?->name,
            'date' => $log->scan_time->format('M d, Y'), 'date_iso' => $log->scan_time->format('Y-m-d'),
            'time' => $log->scan_time->format('g:i:s A'), 'timestamp' => $log->scan_time->toIso8601String(),
            'attendance_type' => $log->attendance_type, 'attendance_label' => $log->attendance_type === 'time_out' ? 'Time Out' : 'Time In',
            'status' => strtolower($log->status ?? 'present'), 'status_label' => ucfirst($log->status ?? 'present'), 'location' => $log->scanner_location,
        ];
    }
}
