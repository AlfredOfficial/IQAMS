<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentRecordsController extends Controller
{
    public function attendance(Request $request): View
    {
        $student = $request->user()->student ?? abort(403, 'No student profile linked to this account.');
        $logs = AttendanceLog::where('user_id', $request->user()->id)
            ->with('schedule.subject')->latest('scan_time')->paginate(15);

        return view('student.attendance', compact('student', 'logs'));
    }

    public function qr(Request $request): View
    {
        $student = $request->user()->student ?? abort(403, 'No student profile linked to this account.');

        return view('student.qr', compact('student'));
    }
}
