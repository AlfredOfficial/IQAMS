<?php

namespace App\Http\Controllers;

use App\Services\PersonnelAttendancePages;
use Illuminate\Http\Request;

class StaffAttendanceController extends Controller
{
    public function history(Request $request, PersonnelAttendancePages $pages)
    {
        return view('staff.attendance.history', $pages->history($request->user(), $request->only([
            'from', 'to', 'status', 'punctuality', 'page', 'per_page',
        ])));
    }

    public function summary(Request $request, PersonnelAttendancePages $pages)
    {
        return view('staff.attendance.summary', $pages->monthly(
            $request->user(),
            (int) $request->input('month', now()->month),
            (int) $request->input('year', now()->year),
        ));
    }

    public function issues(Request $request, PersonnelAttendancePages $pages)
    {
        return view('staff.attendance.issues', $pages->issues($request->user()));
    }
}
