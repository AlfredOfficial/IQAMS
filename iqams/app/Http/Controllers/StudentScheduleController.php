<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StudentScheduleController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student?->load(['course.department', 'section']);

        if (! $student) {
            abort(403, 'No student profile linked to this account.');
        }

        $schedules = $student->section
            ? $student->section->schedules()
                ->select(['id', 'subject_id', 'instructor_id', 'section_id', 'day', 'start_time', 'end_time', 'room'])
                ->with(['subject:id,subject_code,subject_name', 'instructor:id,first_name,last_name'])
                ->orderBy('start_time')->get()
            : collect();

        $scheduleByDay = $schedules->groupBy('day');
        $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return view('student.schedule', compact('student', 'schedules', 'scheduleByDay', 'dayOrder'));
    }
}
