<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DailyPersonnelAttendanceExportService
{
    public function pdf(array $report): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('reports.daily-personnel.pdf', $report)->render());
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    public function xlsx(array $report): string
    {
        $spreadsheet = $this->spreadsheet($report);
        $path = tempnam(sys_get_temp_dir(), 'iqams-report-');

        try {
            (new Xlsx($spreadsheet))->save($path);

            return (string) file_get_contents($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
            if ($path && is_file($path)) {
                unlink($path);
            }
        }
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
}
