<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardData
{
    private const SCAN_LIMIT = 250;

    public function __construct(
        private ScheduleOccurrenceResolver $occurrences,
        private AttendanceSummaryCache $cache,
    ) {}

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

        $analytics = $this->analyticsSnapshot($generatedAt, $today, $tomorrow, $weekStart, $references);

        $payload = [
            'generated_at' => $generatedAt->toIso8601String(),
            'cursor' => $nextCursor->toIso8601String(),
            'stats' => $analytics['stats'],
            'scans' => $scans->map(fn (AttendanceLog $log) => $this->formatLog($log))->values()->all(),
            'charts' => $analytics['charts'],
            'overview' => $analytics['overview'],
        ];

        if ($includeFilters) {
            $payload['filters'] = $references['filters'];
        }

        return $payload;
    }

    public function buildDelta(?Carbon $cursor = null): array
    {
        $generatedAt = now();
        $nextCursor = $generatedAt->copy()->subSecond()->startOfSecond();
        $effectiveCursor = $cursor ?? $generatedAt->copy()->subDay();

        $scans = $this->deltaScanQuery()
            ->where('attendance_logs.updated_at', '>=', $effectiveCursor)
            ->where('attendance_logs.updated_at', '<=', $generatedAt)
            ->orderByDesc('attendance_logs.updated_at')
            ->orderByDesc('attendance_logs.id')
            ->limit(self::SCAN_LIMIT)
            ->get();

        return [
            'generated_at' => $generatedAt->toIso8601String(),
            'cursor' => $nextCursor->toIso8601String(),
            'scans' => $scans->map(fn (AttendanceLog $log) => $this->formatLog($log))->values()->all(),
        ];
    }

    public function analytics(): array
    {
        $generatedAt = now();
        $today = $generatedAt->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $weekStart = $generatedAt->copy()->startOfWeek()->startOfDay();
        $references = DashboardReferenceCache::data();

        return $this->analyticsSnapshot($generatedAt, $today, $tomorrow, $weekStart, $references);
    }

    private function analyticsSnapshot(Carbon $generatedAt, Carbon $today, Carbon $tomorrow, Carbon $weekStart, array $references): array
    {
        return $this->cache->rememberAdminAnalytics(function () use ($generatedAt, $today, $tomorrow, $weekStart, $references): array {
            $roleMetrics = $this->roleMetrics($today, $tomorrow);
            $statusMetrics = $this->statusMetrics($today, $tomorrow);
            $personnelRows = $this->personnelRows($today, $tomorrow);
            [$incompleteUsers, $missingTimeout, $incompleteByRole] = $this->personnelExceptions($personnelRows, $generatedAt->hour);

            $stats = [
                'total_scanned' => $roleMetrics->sum('users'),
                'students' => (int) ($roleMetrics->get('student')['users'] ?? 0),
                'instructors' => (int) ($roleMetrics->get('instructor')['users'] ?? 0),
                'staff' => (int) ($roleMetrics->get('staff')['users'] ?? 0),
                'present' => $this->currentlyPresentCount($generatedAt),
                'absent' => $this->absentUserCount($today, $generatedAt),
                'late' => (int) ($statusMetrics->get('late') ?? 0),
                'missing_timeout' => $missingTimeout,
                'incomplete' => $incompleteUsers,
            ];

            return [
                'stats' => $stats,
                'charts' => [
                    'hourly' => $this->hourly($today, $tomorrow),
                    'roles' => [
                        ['label' => 'Students', 'value' => (int) ($roleMetrics->get('student')['scans'] ?? 0)],
                        ['label' => 'Teaching', 'value' => (int) ($roleMetrics->get('instructor')['scans'] ?? 0)],
                        ['label' => 'Non-teaching', 'value' => (int) ($roleMetrics->get('staff')['scans'] ?? 0)],
                    ],
                    'statuses' => collect(['present', 'late', 'absent', 'incomplete', 'missing'])->map(fn ($status) => [
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
        });
    }

    private function scanQuery(): Builder
    {
        return AttendanceLog::canonical()->with([
            'user.roles', 'user.student.section.course', 'user.student.course',
            'user.instructor.department', 'user.nonTeachingStaff.officeUnit',
            'schedule.subject', 'schedule.section.course', 'schedule.instructor.user',
        ]);
    }

    private function deltaScanQuery(): Builder
    {
        return AttendanceLog::query()
            ->select([
                'attendance_logs.id', 'attendance_logs.user_id', 'attendance_logs.schedule_id',
                'attendance_logs.school_event_id', 'attendance_logs.attendance_type',
                'attendance_logs.scan_time', 'attendance_logs.status', 'attendance_logs.scanner_location',
                'attendance_logs.updated_at',
            ])
            ->where(function ($query): void {
                $query->where('attendance_logs.record_state', 'canonical')
                    ->orWhereNull('attendance_logs.record_state');
            })
            ->with([
                'user:id,username,name,avatar_path',
                'user.roles',
                'user.student:id,user_id,student_no,section_id,course_id',
                'user.student.section:id,section_name,course_id',
                'user.student.section.course:id,course_code',
                'user.student.course:id,course_code',
                'user.instructor:id,user_id,department_id,employee_no',
                'user.instructor.department:id,department_name',
                'user.nonTeachingStaff:id,user_id,office_unit_id,employee_no',
                'user.nonTeachingStaff.officeUnit:id,name',
                'schedule:id,subject_id,section_id,instructor_id',
                'schedule.subject:id,subject_code,subject_name',
                'schedule.section:id,section_name,course_id',
                'schedule.section.course:id,course_code',
                'schedule.instructor:id,user_id',
                'schedule.instructor.user:id,name',
            ]);
    }

    private function roleMetrics(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')
            ->join('users', 'users.id', '=', 'attendance_logs.user_id')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', function ($join) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.guard_name', 'web');
            })
            ->where('attendance_logs.scan_time', '>=', $from)
            ->where('attendance_logs.scan_time', '<', $to)
            ->where('attendance_logs.record_state', 'canonical')
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role_name, COUNT(*) as scans, COUNT(DISTINCT attendance_logs.user_id) as users, SUM(CASE WHEN attendance_logs.status = ? THEN 1 ELSE 0 END) as present, SUM(CASE WHEN attendance_logs.status = ? THEN 1 ELSE 0 END) as late', ['present', 'late'])
            ->get()->mapWithKeys(fn ($row) => [$row->role_name => [
                'scans' => (int) $row->scans, 'users' => (int) $row->users,
                'present' => (int) $row->present, 'late' => (int) $row->late,
            ]]);
    }

    private function statusMetrics(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')->where('scan_time', '>=', $from)->where('scan_time', '<', $to)
            ->where('record_state', 'canonical')
            ->groupBy('status')->selectRaw('status, COUNT(*) as aggregate')->pluck('aggregate', 'status');
    }

    private function personnelRows(Carbon $from, Carbon $to): Collection
    {
        return DB::table('attendance_logs')->join('users', 'users.id', '=', 'attendance_logs.user_id')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', function ($join) {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.guard_name', 'web');
            })->whereIn('roles.name', ['instructor', 'staff'])
            ->where('attendance_logs.scan_time', '>=', $from)->where('attendance_logs.scan_time', '<', $to)
            ->where('attendance_logs.record_state', 'canonical')
            ->orderBy('attendance_logs.scan_time')->get(['attendance_logs.user_id', 'attendance_logs.attendance_type', 'attendance_logs.scan_time', 'roles.name as role_name']);
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

    private function currentlyPresentCount(Carbon $at): int
    {
        $from = $at->copy()->startOfDay()->subDay();

        return AttendanceLog::canonical()
            ->with([
                'schedule:id,section_id,day,start_time,end_time,archived_at',
                'schoolEvent:id,attendance_mode,starts_at,ends_at',
            ])
            ->whereBetween('scan_time', [$from, $at])
            ->whereNotExists(function ($query) use ($from, $at): void {
                $query->selectRaw('1')
                    ->from('attendance_logs as newer')
                    ->whereColumn('newer.user_id', 'attendance_logs.user_id')
                    ->whereBetween('newer.scan_time', [$from, $at])
                    ->where(function ($query): void {
                        $query->where('newer.record_state', 'canonical')
                            ->orWhereNull('newer.record_state');
                    })
                    ->where(function ($query): void {
                        $query->whereColumn('newer.scan_time', '>', 'attendance_logs.scan_time')
                            ->orWhere(function ($query): void {
                                $query->whereColumn('newer.scan_time', 'attendance_logs.scan_time')
                                    ->whereColumn('newer.id', '>', 'attendance_logs.id');
                            });
                    });
            })
            ->where('attendance_type', 'time_in')
            ->whereIn('status', ['present', 'late'])
            ->select(['id', 'user_id', 'schedule_id', 'school_event_id', 'attendance_type', 'scan_time', 'status'])
            ->get()
            ->filter(fn (AttendanceLog $log) => $this->isCurrentlyPresent($log, $at))
            ->count();
    }

    private function absentUserCount(Carbon $date, Carbon $at): int
    {
        $studentAbsent = AttendanceLog::canonical()
            ->where('scan_time', '>=', $date->copy()->startOfDay())
            ->where('scan_time', '<', $date->copy()->addDay()->startOfDay())
            ->where('status', 'absent')
            ->where(function ($query): void {
                $query->whereNotNull('schedule_id')->orWhereNotNull('school_event_id');
            })
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('student', fn ($student) => $student->where('status', 'active')))
            ->with(['schedule:id,section_id,day,start_time,end_time,archived_at', 'schoolEvent:id,attendance_mode,starts_at,ends_at'])
            ->select(['id', 'user_id', 'schedule_id', 'school_event_id', 'attendance_type', 'scan_time', 'status'])
            ->get()
            ->filter(fn (AttendanceLog $log): bool => $this->studentAbsenceCutoffPassed($log, $at))
            ->pluck('user_id')
            ->unique()
            ->count();

        $rolesPastCutoff = collect(['instructor', 'staff'])
            ->filter(fn (string $role): bool => $this->personnelCutoffPassed($role, $at))
            ->values();

        $personnelAbsent = 0;
        if ($rolesPastCutoff->isNotEmpty()) {
            $personnelAbsent = User::query()
                ->join('model_has_roles', function ($join): void {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', User::class);
                })
                ->join('roles', function ($join): void {
                    $join->on('roles.id', '=', 'model_has_roles.role_id')
                        ->where('roles.guard_name', 'web');
                })
                ->where('status', 'active')
                ->whereIn('roles.name', $rolesPastCutoff->all())
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('roles.name', 'instructor')->whereHas('instructor');
                    })->orWhere(function ($query): void {
                        $query->where('roles.name', 'staff')->whereHas('nonTeachingStaff');
                    });
                })
                ->whereDoesntHave('leaveRequests', function ($query) use ($date): void {
                    $query->where('status', 'approved')
                        ->where('start_date', '<', $date->copy()->addDay()->toDateString())
                        ->where('end_date', '>=', $date->toDateString());
                })
                ->whereDoesntHave('attendanceLogs', function ($query) use ($date): void {
                    $query->where(function ($query): void {
                        $query->where('record_state', 'canonical')->orWhereNull('record_state');
                    })->where('scan_time', '>=', $date->copy()->startOfDay())
                        ->where('scan_time', '<', $date->copy()->addDay()->startOfDay())
                        ->where('attendance_period', 'morning_in')
                        ->where('attendance_type', 'time_in')
                        ->whereIn('status', ['present', 'late']);
                })
                ->groupBy('roles.name')
                ->selectRaw('roles.name as role_name, COUNT(users.id) as aggregate')
                ->get()
                ->sum('aggregate');
        }

        return $studentAbsent + $personnelAbsent;
    }

    private function studentAbsenceCutoffPassed(AttendanceLog $log, Carbon $at): bool
    {
        if ($log->schedule) {
            $occurrence = $this->occurrences->forDate($log->schedule, $at->copy()->startOfDay());

            return $occurrence !== null && $at->greaterThan($occurrence->presentUntil);
        }

        return $log->schoolEvent?->ends_at?->lessThan($at) ?? false;
    }

    private function personnelCutoffPassed(string $role, Carbon $at): bool
    {
        $end = config("attendance.personnel_windows.{$role}.morning_in.end");
        $cutoff = $at->copy()->startOfDay()->setTimeFromTimeString((string) $end);

        return $at->greaterThan($cutoff);
    }

    private function isCurrentlyPresent(AttendanceLog $log, Carbon $at): bool
    {
        if ($log->attendance_type !== 'time_in' || ! in_array($log->status, ['present', 'late'], true)) {
            return false;
        }

        if ($log->schedule) {
            $occurrence = $this->occurrences->resolveAt($log->schedule, $at);

            return $occurrence !== null
                && $log->scan_time->betweenIncluded($occurrence->opensAt, $occurrence->endsAt);
        }

        if ($log->schoolEvent) {
            return $log->schoolEvent->starts_at->lessThanOrEqualTo($at)
                && $log->schoolEvent->ends_at->greaterThanOrEqualTo($at);
        }

        return $log->scan_time->copy()->timezone(config('app.timezone'))->isSameDay($at);
    }

    private function hourly(Carbon $from, Carbon $to): array
    {
        $expression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', scan_time) AS INTEGER)"
            : 'HOUR(scan_time)';
        $counts = DB::table('attendance_logs')->where('scan_time', '>=', $from)->where('scan_time', '<', $to)
            ->where('record_state', 'canonical')
            ->selectRaw("{$expression} as bucket, COUNT(*) as aggregate")->groupBy('bucket')->pluck('aggregate', 'bucket');

        return collect(range(6, 20))->map(fn ($hour) => [
            'label' => Carbon::createFromTime($hour)->format('g A'), 'value' => (int) ($counts->get($hour) ?? 0),
        ])->all();
    }

    private function weekly(Carbon $from, Carbon $to): array
    {
        $expression = DB::getDriverName() === 'sqlite' ? 'date(scan_time)' : 'DATE(scan_time)';
        $counts = DB::table('attendance_logs')->whereBetween('scan_time', [$from, $to])
            ->where('record_state', 'canonical')
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
            ->where('attendance_logs.record_state', 'canonical')
            ->groupBy('departments.department_code')->orderByDesc('aggregate')->limit(8)
            ->selectRaw("COALESCE(departments.department_code, 'Unassigned') as label, COUNT(*) as aggregate")
            ->get()->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->aggregate])->all();
    }

    private function subjects(Carbon $from, Carbon $to): array
    {
        return DB::table('attendance_logs')->join('schedules', 'schedules.id', '=', 'attendance_logs.schedule_id')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->where('attendance_logs.scan_time', '>=', $from)->where('attendance_logs.scan_time', '<', $to)
            ->where('attendance_logs.record_state', 'canonical')
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
        $role = strtolower((string) ($log->user?->primaryRoleName() ?? 'unknown'));
        $student = $log->user?->student;
        $instructor = $log->user?->instructor;
        $staff = $log->user?->nonTeachingStaff;
        $section = $student?->section ?? $log->schedule?->section;
        $department = $instructor?->department?->department_name;
        $officeUnit = $staff?->officeUnit?->name;
        $displayName = $staff?->fullName() ?? $log->user?->name ?? 'Unknown user';

        return [
            'id' => $log->id, 'user_id' => $log->user_id,
            'identifier' => $student?->student_no ?? $instructor?->employee_no ?? $staff?->employee_no ?? $log->user?->username ?? '—',
            'name' => $displayName,
            'initials' => collect(explode(' ', $displayName))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'avatar' => $log->user?->avatar_url, 'role_key' => $role,
            'role' => match ($role) {
                'instructor' => 'Teaching Personnel', 'staff' => 'Non-teaching Personnel', 'student' => 'Student', default => ucfirst($role)
            },
            'section' => $section?->section_name, 'course' => $student?->course?->course_code ?? $section?->course?->course_code,
            'department' => $role === 'staff' ? $officeUnit : $department,
            'group' => $role === 'student' ? ($section?->section_name ?? 'Unassigned') : (($role === 'staff' ? $officeUnit : $department) ?? 'Unassigned'),
            'subject' => $log->schedule?->subject?->subject_name, 'subject_code' => $log->schedule?->subject?->subject_code,
            'instructor' => $log->schedule?->instructor?->user?->name,
            'date' => $log->scan_time->format('M d, Y'), 'date_iso' => $log->scan_time->format('Y-m-d'),
            'time' => $log->scan_time->format('g:i:s A'), 'timestamp' => $log->scan_time->toIso8601String(),
            'attendance_type' => $log->attendance_type, 'attendance_label' => $log->attendance_type === 'time_out' ? 'Time Out' : 'Time In',
            'status' => strtolower($log->status ?? 'present'), 'status_label' => ucfirst($log->status ?? 'present'), 'location' => $log->scanner_location,
        ];
    }
}
