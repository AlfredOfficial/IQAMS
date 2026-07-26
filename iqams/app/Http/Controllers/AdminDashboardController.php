<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceLog;
use App\Models\Instructor;
use App\Models\Student;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalStudents = Student::count();
        $totalInstructors = Instructor::count();
        $todayLogs = AttendanceLog::whereDate('scan_time', today())->count();

        return view('dashboard', compact('totalStudents', 'totalInstructors', 'todayLogs'));
    }
}
