<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PersonnelAttendanceReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class DailyPersonnelAttendanceReportController extends Controller
{
    public function __construct(private PersonnelAttendanceReportService $reports) {}

    public function index(Request $request): View
    {
        [$date, $filters] = $this->validated($request);

        return view('reports.daily-personnel.index', $this->reports->getDailyReport($date, $filters) + $this->reports->filterOptions());
    }

    public function pdf(Request $request): Response
    {
        [$date, $filters] = $this->validated($request);
        $report = $this->reports->getDailyReport($date, $filters);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('reports.daily-personnel.pdf', $report)->render());
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($date, 'pdf').'"',
        ]);
    }

    public function excel(Request $request): Response
    {
        [$date, $filters] = $this->validated($request);
        $report = $this->reports->getDailyReport($date, $filters);
        $spreadsheet = $this->spreadsheet($report);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $this->filename($date, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validated(Request $request): array
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'personnel_type' => ['nullable', Rule::in(['instructor', 'staff'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'office_unit_id' => ['nullable', 'integer', 'exists:office_units,id'],
            'personnel_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (($filters['personnel_type'] ?? null) === 'staff' && ! empty($filters['department_id'])) {
            throw ValidationException::withMessages(['department_id' => 'Department filters only apply to instructors.']);
        }
        if (($filters['personnel_type'] ?? null) === 'instructor' && ! empty($filters['office_unit_id'])) {
            throw ValidationException::withMessages(['office_unit_id' => 'Office/unit filters only apply to non-teaching staff.']);
        }
        if (! empty($filters['department_id']) && ! empty($filters['office_unit_id'])) {
            throw ValidationException::withMessages(['office_unit_id' => 'Choose either a department or an office/unit filter.']);
        }
        if (! empty($filters['personnel_id'])) {
            $isPersonnel = User::whereKey($filters['personnel_id'])
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereHas('instructor')->orWhereHas('nonTeachingStaff'))
                ->exists();
            if (! $isPersonnel) {
                throw ValidationException::withMessages(['personnel_id' => 'Select an active instructor or non-teaching staff member.']);
            }
        }

        $date = Carbon::createFromFormat('Y-m-d', $filters['date'] ?? today()->toDateString(), config('app.timezone'))->startOfDay();
        unset($filters['date']);

        return [$date, array_filter($filters, fn ($value) => $value !== null && $value !== '')];
    }

    private function spreadsheet(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daily Attendance');
        $sheet->setCellValue('A1', 'DANAO TECHNOLOGICAL COLLEGE');
        $sheet->setCellValue('A2', 'DAILY ATTENDANCE REPORT');
        $sheet->setCellValue('A3', 'Date: '.$report['date']->format('F j, Y'));
        $sheet->mergeCells('A1:E1')->mergeCells('A2:E2')->mergeCells('A3:E3');
        $sheet->setCellValue('A5', 'Name')->setCellValue('B5', 'MORNING')->setCellValue('D5', 'AFTERNOON');
        $sheet->mergeCells('A5:A6')->mergeCells('B5:C5')->mergeCells('D5:E5');
        $sheet->fromArray(['TIME-IN', 'TIME-OUT', 'TIME-IN', 'TIME-OUT'], null, 'B6');

        $rowNumber = 7;
        foreach ($report['rows'] as $row) {
            $sheet->fromArray([
                $row['name'], $row['morning_time_in'], $row['morning_time_out'],
                $row['afternoon_time_in'], $row['afternoon_time_out'],
            ], null, 'A'.$rowNumber++);
        }

        $signatureRow = $rowNumber + 2;
        $sheet->setCellValue('A'.$signatureRow, 'Prepared by: ______________________________');
        $sheet->setCellValue('C'.$signatureRow, 'Checked by: _______________________________');
        $sheet->setCellValue('E'.$signatureRow, 'Date: ____________________');
        $sheet->getStyle('A1:E3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:E2')->getFont()->setBold(true);
        $sheet->getStyle('A5:E6')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);
        if ($rowNumber > 7) {
            $sheet->getStyle('A5:E'.($rowNumber - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        } else {
            $sheet->getStyle('A5:E6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $sheet->getStyle('B7:E'.max(7, $rowNumber - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(38);
        foreach (['B', 'C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.35)->setRight(0.35);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 6);

        return $spreadsheet;
    }

    private function filename(Carbon $date, string $extension): string
    {
        return 'daily-personnel-attendance-'.$date->toDateString().'.'.$extension;
    }
}
