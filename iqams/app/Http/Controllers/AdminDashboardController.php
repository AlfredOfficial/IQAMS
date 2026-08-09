<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'dashboardData' => $this->dashboardData(),
        ]);
    }

    public function realtime(): JsonResponse
    {
        return response()->json($this->dashboardData());
    }

    private function dashboardData(): array
    {
        $today = today();
        $relations = [
            'user.role',
            'user.student.section.course',
            'user.student.course',
            'user.instructor.department',
            'user.nonTeachingStaff',
            'schedule.subject',
            'schedule.section.course',
            'schedule.instructor.user',
        ];

        $todayLogs = AttendanceLog::with($relations)
            ->whereDate('scan_time', $today)
            ->latest('scan_time')
            ->get();

        $recentLogs = AttendanceLog::with($relations)
            ->latest('scan_time')
            ->limit(250)
            ->get();

        $staffLogs = $todayLogs->filter(fn ($log) => in_array($this->roleName($log), ['instructor', 'staff'], true));
        $staffByUser = $staffLogs->groupBy('user_id');
        $hour = now()->hour;
        $expectedStaffScans = $hour < 12 ? 1 : ($hour < 13 ? 2 : ($hour < 17 ? 3 : 4));
        $incompleteUsers = $staffByUser->filter(fn ($logs) => $logs->count() < $expectedStaffScans)->count();
        $missingTimeout = in_array($expectedStaffScans, [2, 4], true) ? $staffByUser->filter(function ($logs) {
            $latest = $logs->sortByDesc('scan_time')->first();
            return $latest?->attendance_type === 'time_in';
        })->count() : 0;

        $uniqueToday = $todayLogs->unique('user_id');
        $studentLogs = $todayLogs->filter(fn ($log) => $this->roleName($log) === 'student');
        $instructorLogs = $todayLogs->filter(fn ($log) => $this->roleName($log) === 'instructor');
        $nonTeachingLogs = $todayLogs->filter(fn ($log) => $this->roleName($log) === 'staff');

        $statuses = ['present', 'late', 'incomplete', 'missing'];
        $statusCounts = collect($statuses)->mapWithKeys(fn ($status) => [
            $status => $status === 'incomplete'
                ? $incompleteUsers
                : ($status === 'missing' ? $missingTimeout : $todayLogs->where('status', $status)->count()),
        ]);

        $weekStart = now()->startOfWeek();
        $weekLogs = AttendanceLog::whereBetween('scan_time', [$weekStart, now()->endOfDay()])->get(['scan_time']);

        return [
            'generated_at' => now()->toIso8601String(),
            'stats' => [
                'total_scanned' => $uniqueToday->count(),
                'students' => $studentLogs->unique('user_id')->count(),
                'instructors' => $instructorLogs->unique('user_id')->count(),
                'staff' => $nonTeachingLogs->unique('user_id')->count(),
                'present' => $todayLogs->groupBy('user_id')->filter(fn ($logs) => $logs->sortByDesc('scan_time')->first()?->attendance_type === 'time_in')->count(),
                'late' => $todayLogs->where('status', 'late')->count(),
                'missing_timeout' => $missingTimeout,
                'incomplete' => $incompleteUsers,
            ],
            'scans' => $recentLogs->map(fn ($log) => $this->formatLog($log))->values(),
            'charts' => [
                'hourly' => collect(range(6, 20))->map(fn ($hour) => [
                    'label' => Carbon::createFromTime($hour)->format('g A'),
                    'value' => $todayLogs->filter(fn ($log) => $log->scan_time->hour === $hour)->count(),
                ])->values(),
                'roles' => [
                    ['label' => 'Students', 'value' => $studentLogs->count()],
                    ['label' => 'Teaching', 'value' => $instructorLogs->count()],
                    ['label' => 'Non-teaching', 'value' => $nonTeachingLogs->count()],
                ],
                'statuses' => $statusCounts->map(fn ($value, $label) => ['label' => ucfirst($label), 'value' => $value])->values(),
                'departments' => $todayLogs->groupBy(fn ($log) => $log->user?->instructor?->department?->department_code ?? 'Unassigned')
                    ->map(fn ($logs, $label) => ['label' => $label, 'value' => $logs->count()])->sortByDesc('value')->take(8)->values(),
                'subjects' => $studentLogs->groupBy(fn ($log) => $log->schedule?->subject?->subject_code ?? 'Unassigned')
                    ->map(fn ($logs, $label) => ['label' => $label, 'value' => $logs->count()])->sortByDesc('value')->take(8)->values(),
                'weekly' => collect(range(0, 6))->map(function ($offset) use ($weekStart, $weekLogs) {
                    $day = $weekStart->copy()->addDays($offset);
                    return ['label' => $day->format('D'), 'value' => $weekLogs->filter(fn ($log) => $log->scan_time->isSameDay($day))->count()];
                })->values(),
            ],
            'overview' => [
                $this->overviewRow('Students', $studentLogs, User::whereHas('role', fn ($q) => $q->where('role_name', 'student'))->count(), false),
                $this->overviewRow('Teaching Personnel', $instructorLogs, User::whereHas('role', fn ($q) => $q->where('role_name', 'instructor'))->count(), true),
                $this->overviewRow('Non-teaching Personnel', $nonTeachingLogs, User::whereHas('role', fn ($q) => $q->where('role_name', 'staff'))->count(), true),
            ],
            'filters' => [
                'departments' => Department::orderBy('department_name')->pluck('department_name')->values(),
                'sections' => Section::orderBy('section_name')->pluck('section_name')->values(),
                'subjects' => Subject::orderBy('subject_name')->pluck('subject_name')->values(),
            ],
        ];
    }

    private function roleName(AttendanceLog $log): string
    {
        return strtolower($log->user?->role?->role_name ?? 'unknown');
    }

    private function formatLog(AttendanceLog $log): array
    {
        $role = $this->roleName($log);
        $student = $log->user?->student;
        $instructor = $log->user?->instructor;
        $staff = $log->user?->nonTeachingStaff;
        $section = $student?->section ?? $log->schedule?->section;
        $identifier = $student?->student_no ?? $instructor?->employee_no ?? $staff?->employee_no ?? $log->user?->username ?? '—';
        $department = $instructor?->department?->department_name;

        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'identifier' => $identifier,
            'name' => $log->user?->name ?? 'Unknown user',
            'initials' => collect(explode(' ', $log->user?->name ?? '?'))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'avatar' => $log->user?->avatar_url,
            'role_key' => $role,
            'role' => match ($role) { 'instructor' => 'Teaching Personnel', 'staff' => 'Non-teaching Personnel', 'student' => 'Student', default => ucfirst($role) },
            'section' => $section?->section_name,
            'course' => $student?->course?->course_code ?? $section?->course?->course_code,
            'department' => $department ?? ($role === 'staff' ? 'Administration' : null),
            'group' => $role === 'student' ? ($section?->section_name ?? 'Unassigned') : ($department ?? ($role === 'staff' ? 'Administration' : 'Unassigned')),
            'subject' => $log->schedule?->subject?->subject_name,
            'subject_code' => $log->schedule?->subject?->subject_code,
            'instructor' => $log->schedule?->instructor?->user?->name,
            'date' => $log->scan_time->format('M d, Y'),
            'date_iso' => $log->scan_time->format('Y-m-d'),
            'time' => $log->scan_time->format('g:i:s A'),
            'timestamp' => $log->scan_time->toIso8601String(),
            'attendance_type' => $log->attendance_type,
            'attendance_label' => $log->attendance_type === 'time_out' ? 'Time Out' : 'Time In',
            'status' => strtolower($log->status ?? 'present'),
            'status_label' => ucfirst($log->status ?? 'present'),
            'location' => $log->scanner_location,
        ];
    }

    private function overviewRow(string $label, Collection $logs, int $total, bool $fourScan): array
    {
        $byUser = $logs->groupBy('user_id');
        $scanned = $byUser->count();
        $complete = $fourScan ? $byUser->filter(fn ($items) => $items->count() >= 4)->count() : $logs->where('status', 'present')->count();
        $secondary = $fourScan ? max(0, $scanned - $complete) : $logs->where('status', 'late')->count();

        return [
            'label' => $label,
            'scanned' => $scanned,
            'total' => $total,
            'primary_label' => $fourScan ? 'Complete' : 'Present',
            'primary' => $complete,
            'secondary_label' => $fourScan ? 'Incomplete' : 'Late',
            'secondary' => $secondary,
            'percentage' => $total > 0 ? min(100, (int) round(($scanned / $total) * 100)) : 0,
        ];
    }
}
