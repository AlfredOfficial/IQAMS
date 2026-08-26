<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\PersonnelAttendanceSummary;
use App\Services\StudentAttendanceWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InstructorAttendanceController extends Controller
{
    public function history(Request $request, PersonnelAttendanceSummary $summary)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->input('to', now()->toDateString()));
        $days = $summary->days($request->user(), $from, $to, false)->reverse()->values();
        if ($status = $request->input('status')) {
            $days = $status === 'Incomplete' ? $days->where('isIncomplete', true)->values() : $days->where('status', $status)->values();
        }
        if ($punctuality = $request->input('punctuality')) $days = $days->where('punctuality', $punctuality)->values();
        return view('instructor.history', compact('days', 'from', 'to'));
    }

    public function summary(Request $request, PersonnelAttendanceSummary $service)
    {
        $month = max(1, min(12, (int) $request->input('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $from = Carbon::create($year, $month, 1);
        $to = $from->isFuture() ? $from->copy()->subDay() : $from->copy()->endOfMonth()->min(today());
        $days = $service->days($request->user(), $from, $to, true);
        $totals = $service->totals($days);
        return view('instructor.summary', compact('days', 'totals', 'month', 'year'));
    }

    public function issues(Request $request, PersonnelAttendanceSummary $service)
    {
        $from = now()->startOfMonth();
        $days = $service->days($request->user(), $from, today(), true)
            ->filter(fn ($day) => $day['status'] === 'Absent' || $day['isIncomplete'] || $day['late'] || $day['early'])->reverse()->values();
        return view('instructor.issues', compact('days'));
    }

    public function schedule(Request $request)
    {
        $instructor = $request->user()->instructor;
        abort_unless($instructor, 403);
        $dayOrder = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
        $scheduleGroups = $instructor->schedules()->with(['subject', 'section'])
            ->orderBy('start_time')->get()
            ->groupBy(fn (Schedule $schedule) => implode('|', [
                $schedule->subject_id,
                $schedule->section_id,
                substr((string) $schedule->start_time, 0, 5),
                substr((string) $schedule->end_time, 0, 5),
                $schedule->room,
            ]))
            ->map(function ($schedules) use ($dayOrder) {
                $first = $schedules->first();
                $byDay = $schedules->keyBy('day');
                $days = $dayOrder->filter(fn (string $day) => $byDay->has($day))->values();

                return [
                    'key' => 'schedule-'.$first->id,
                    'subject_code' => $first->subject?->subject_code ?? 'Subject',
                    'subject_name' => $first->subject?->subject_name ?? 'Unnamed subject',
                    'section' => $first->section?->section_name ?? 'No section',
                    'room' => $first->room ?: 'TBD',
                    'start_time' => Carbon::parse($first->start_time)->format('g:i A'),
                    'end_time' => Carbon::parse($first->end_time)->format('g:i A'),
                    'days_label' => $days->map(fn (string $day) => $this->shortDay($day))->implode(''),
                    'days' => $days->map(fn (string $day) => [
                        'name' => $day,
                        'label' => ucfirst($day),
                        'schedule_id' => $byDay[$day]->id,
                    ])->values(),
                ];
            })->values();

        return view('instructor.schedule', compact('instructor', 'scheduleGroups'));
    }

    public function classAttendance(
        Request $request,
        Schedule $schedule,
        StudentAttendanceWindow $window,
    ): JsonResponse {
        $instructor = $request->user()->instructor;
        abort_unless($instructor && $schedule->instructor_id === $instructor->id, 403);

        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::createFromFormat('!Y-m-d', $validated['date'], config('app.timezone'));

        if (strtolower($date->format('l')) !== strtolower($schedule->day)) {
            throw ValidationException::withMessages([
                'date' => 'The selected date is not a valid class day for this schedule.',
            ]);
        }

        $schedule->loadMissing(['subject', 'section']);
        $students = Student::with('user')
            ->where('section_id', $schedule->section_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->orderBy('last_name')->orderBy('first_name')->get();

        $logs = AttendanceLog::where('schedule_id', $schedule->id)
            ->whereDate('scan_time', $date->toDateString())
            ->whereIn('user_id', $students->pluck('user_id'))
            ->orderBy('scan_time')->get()->keyBy('user_id');
        $cutoffPassed = now(config('app.timezone'))->greaterThan($window->presentUntil($schedule, $date));

        $rows = $students->map(function (Student $student) use ($logs, $cutoffPassed) {
            $log = $logs->get($student->user_id);
            $status = $log?->status ?? ($cutoffPassed ? 'absent' : 'pending');

            return [
                'student_no' => $student->student_no,
                'name' => $student->fullName(),
                'time_in' => $log?->scan_time?->timezone(config('app.timezone'))->format('g:i A'),
                'status' => $status,
                'recorded' => (bool) $log,
            ];
        })->values();

        return response()->json([
            'class' => [
                'schedule_id' => $schedule->id,
                'subject_code' => $schedule->subject?->subject_code ?? 'Subject',
                'subject_name' => $schedule->subject?->subject_name ?? 'Unnamed subject',
                'section' => $schedule->section?->section_name ?? 'No section',
                'room' => $schedule->room ?: 'TBD',
                'date' => $date->toDateString(),
                'date_label' => $date->format('l, F j, Y'),
                'time_label' => Carbon::parse($schedule->start_time)->format('g:i A').' - '.Carbon::parse($schedule->end_time)->format('g:i A'),
            ],
            'summary' => [
                'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                'absent' => $rows->where('status', 'absent')->count(),
                'excused' => $rows->where('status', 'excused')->count(),
                'pending' => $rows->where('status', 'pending')->count(),
            ],
            'has_students' => $rows->isNotEmpty(),
            'has_records' => $logs->isNotEmpty(),
            'students' => $rows,
        ])->header('Cache-Control', 'no-store');
    }

    private function shortDay(string $day): string
    {
        return match ($day) {
            'tuesday' => 'T',
            'thursday' => 'Th',
            'saturday' => 'Sa',
            'sunday' => 'Su',
            default => strtoupper(substr($day, 0, 1)),
        };
    }
}
