<?php

namespace App\Http\Controllers;

use App\Services\DailyPersonnelAttendanceReportFilters;
use App\Services\PersonnelAttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyPersonnelAttendanceReportController extends Controller
{
    public function __construct(
        private PersonnelAttendanceReportService $reports,
        private DailyPersonnelAttendanceReportFilters $filters,
    ) {}

    public function index(Request $request): View
    {
        [$date, $filters] = $this->filters->validate($request);

        return view('reports.daily-personnel.index', $this->reports->getDailyReport($date, $filters) + $this->reports->filterOptions());
    }
}
