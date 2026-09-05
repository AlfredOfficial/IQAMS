<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\PersonnelAttendancePages;
use App\Services\ScheduleOccurrenceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstructorAttendanceController extends Controller
{
    public function history(Request $request, PersonnelAttendancePages $pages)
    {
        return view('instructor.history', $pages->history($request->user(), $request->only([
            'from', 'to', 'status', 'punctuality', 'page', 'per_page',
        ])));
    }

    public function summary(Request $request, PersonnelAttendancePages $pages)
    {
        return view('instructor.summary', $pages->monthly(
            $request->user(),
            (int) $request->input('month', now()->month),
            (int) $request->input('year', now()->year),
        ));
    }

    public function issues(Request $request, PersonnelAttendancePages $pages)
    {
        return view('instructor.issues', $pages->issues($request->user()));
    }

    public function schedule(Request $request)
    {
        $instructor = $request->user()->instructor;
        abort_unless($instructor, 403);
        $dayOrder = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
        $scheduleGroups = $instructor->schedules()
            ->select(['id', 'subject_id', 'instructor_id', 'section_id', 'day', 'start_time', 'end_time', 'room', 'recurring_schedule_group_id'])
            ->with(['subject:id,subject_code,subject_name', 'section:id,section_name'])
            ->orderBy('start_time')->get()
            ->groupBy(fn (Schedule $schedule) => $schedule->recurring_schedule_group_id ?? implode('|', [
                'legacy',
                $schedule->subject_id,
                $schedule->section_id,
                $schedule->instructor_id,
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
        ScheduleOccurrenceResolver $occurrences,
    ): JsonResponse {
        return response()->json($this->classAttendanceData($request, $schedule, $occurrences))
            ->header('Cache-Control', 'no-store');
    }

    public function downloadClassAttendance(
        Request $request,
        Schedule $schedule,
        ScheduleOccurrenceResolver $occurrences,
    ): StreamedResponse {
        $attendance = $this->classAttendanceData($request, $schedule, $occurrences);
        $class = $attendance['class'];
        $filename = 'class-attendance-'.Str::slug($class['subject_code']).'-'.Str::slug($class['section']).'-'.$class['date'].'.xlsx';

        return response()->streamDownload(function () use ($attendance, $class): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Class Attendance');

            $sheet->mergeCells('A1:E1')->setCellValue('A1', 'CLASS ATTENDANCE REPORT');
            $sheet->mergeCells('A2:E2')->setCellValue('A2', $class['subject_code'].' - '.$class['subject_name']);
            $sheet->setCellValue('A3', 'Instructor:');
            $sheet->mergeCells('B3:E3')->setCellValue('B3', $class['instructor']);
            $sheet->setCellValue('A4', 'Section:');
            $sheet->mergeCells('B4:E4')->setCellValue('B4', $class['section']);
            $sheet->setCellValue('A5', 'Date and time:');
            $sheet->mergeCells('B5:E5')->setCellValue('B5', $class['date_label'].' | '.$class['time_label']);
            $sheet->fromArray(['No.', 'Student No.', 'Student Name', 'Time In', 'Status'], null, 'A7');

            $rowNumber = 8;
            foreach ($attendance['students'] as $index => $student) {
                $sheet->fromArray([
                    $index + 1,
                    $student['student_no'],
                    $student['name'],
                    $student['time_in'] ?? '-',
                    ucfirst($student['status']),
                ], null, 'A'.$rowNumber++);
            }

            $summaryRow = $rowNumber + 2;
            $sheet->setCellValue('A'.$summaryRow, 'Summary');
            $sheet->setCellValue('A'.($summaryRow + 1), 'Present / Late');
            $sheet->setCellValue('B'.($summaryRow + 1), $attendance['summary']['present']);
            $sheet->setCellValue('C'.($summaryRow + 1), 'Absent');
            $sheet->setCellValue('D'.($summaryRow + 1), $attendance['summary']['absent']);
            $sheet->setCellValue('A'.($summaryRow + 2), 'Excused');
            $sheet->setCellValue('B'.($summaryRow + 2), $attendance['summary']['excused']);
            $sheet->setCellValue('C'.($summaryRow + 2), 'Pending');
            $sheet->setCellValue('D'.($summaryRow + 2), $attendance['summary']['pending']);

            $sheet->getStyle('A1:E2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1:E2')->getFont()->setBold(true);
            $sheet->getStyle('A1')->getFont()->setSize(16);
            $sheet->getStyle('A7:E7')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            ]);
            $sheet->getStyle('A7:E'.max(7, $rowNumber - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A8:A'.max(8, $rowNumber - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D8:E'.max(8, $rowNumber - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$summaryRow.':D'.$summaryRow)->getFont()->setBold(true);
            $sheet->getStyle('A'.$summaryRow.':D'.($summaryRow + 2))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(18);
            $sheet->getColumnDimension('C')->setWidth(34);
            $sheet->getColumnDimension('D')->setWidth(16);
            $sheet->getColumnDimension('E')->setWidth(14);
            $sheet->freezePane('A8');

            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function classAttendanceData(
        Request $request,
        Schedule $schedule,
        ScheduleOccurrenceResolver $occurrences,
    ): array {
        $instructor = $request->user()->instructor;
        abort_unless($instructor && $schedule->instructor_id === $instructor->id, 403);

        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::createFromFormat('!Y-m-d', $validated['date'], config('app.timezone'));

        $occurrence = $occurrences->forDate($schedule, $date);

        if (! $occurrence) {
            throw ValidationException::withMessages([
                'date' => 'The selected date is not a valid class day for this schedule.',
            ]);
        }

        $schedule->loadMissing(['subject:id,subject_code,subject_name', 'section:id,section_name']);
        $students = Student::query()
            ->select(['id', 'user_id', 'student_no', 'first_name', 'last_name', 'middle_name', 'section_id', 'status'])
            ->where('section_id', $schedule->section_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->orderBy('last_name')->orderBy('first_name')->get();

        $logs = AttendanceLog::canonical()->where('schedule_id', $schedule->id)
            ->whereBetween('scan_time', [$occurrence->opensAt, $occurrence->endsAt])
            ->where('attendance_type', 'time_in')
            ->whereIn('user_id', $students->pluck('user_id'))
            ->select(['id', 'user_id', 'scan_time', 'status'])
            ->orderBy('scan_time')->get()->keyBy('user_id');
        $cutoffPassed = now(config('app.timezone'))->greaterThan($occurrence->presentUntil);

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

        return [
            'class' => [
                'schedule_id' => $schedule->id,
                'subject_code' => $schedule->subject?->subject_code ?? 'Subject',
                'subject_name' => $schedule->subject?->subject_name ?? 'Unnamed subject',
                'instructor' => $instructor->fullName(),
                'section' => $schedule->section?->section_name ?? 'No section',
                'room' => $schedule->room ?: 'TBD',
                'date' => $date->toDateString(),
                'date_label' => $date->format('l, F j, Y'),
                'time_label' => $occurrence->startsAt->format('g:i A').' - '.$occurrence->endsAt->format('g:i A'),
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
        ];
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
