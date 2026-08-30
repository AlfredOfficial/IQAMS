<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Personnel Attendance — {{ $date->toDateString() }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { margin: 0; color: #111; font-family: "DejaVu Sans", sans-serif; font-size: 9px; }
        .attendance-document { width: 100%; }
        .report-heading { margin-bottom: 12px; text-align: center; }
        .report-heading h2 { margin: 0; font-size: 14px; letter-spacing: .5px; }
        .report-heading h3 { margin: 3px 0 0; font-size: 12px; }
        .report-heading p { margin: 5px 0 0; font-size: 10px; }
        .attendance-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .attendance-table th, .attendance-table td { border: 1px solid #333; padding: 4px 5px; }
        .attendance-table th { background: #eee; text-align: center; vertical-align: middle; font-weight: bold; }
        .attendance-table td:not(:first-child) { text-align: center; }
        .attendance-table .name-column { width: 36%; }
        .attendance-table thead { display: table-header-group; }
        .attendance-table tr { page-break-inside: avoid; }
        .empty-report { text-align: center; }
        .report-signatures { margin-top: 22px; width: 100%; page-break-inside: avoid; }
        .report-signatures p { display: inline-block; width: 32%; margin: 0; white-space: nowrap; }
        .report-signatures span { display: inline-block; width: 145px; border-bottom: 1px solid #111; }
        .report-signatures .date-line { width: 90px; }
    </style>
</head>
<body>
    @include('reports.daily-personnel._report')
</body>
</html>
