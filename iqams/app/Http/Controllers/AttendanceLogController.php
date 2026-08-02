<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    private const GRACE_MINUTES = 15;

    public function index(Request $request)
    {
        $query = AttendanceLog::with(['user', 'schedule.subject', 'schedule.section']);

        if($request->filled('date')) {
            $query->whereDate('scan_time', $request->date('date'));
        }

        if($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $logs = $query->latest('scan_time')->paginate(15)->withQueryString();

        $schedules = Schedule::with(['subject', 'section'])->orderBy('day')->get();

        //build a combined list of loggable people
        //for the person dropdown each carrying their linked user_id

        $people = collect()
            ->concat(Student::with('user')->get()->map(fn ($s) => [
                'user_id' => $s->user_id,
                'label' => "{$s->first_name} {$s->last_name} (Student)",
            ]))

            ->concat(Instructor::with('user')->get()->map(fn ($i) => [
                'user_id' => $i->user_id,
                'label' => "{$i->first_name} {$i->last_name} (Instructor)",
            ]))
            
            ->concat(NonTeachingStaff::with('user')->get()->map(fn ($s) => [
                'user_id' => $s->user_id,
                'label' => "{$s->first_name} {$s->last_name} (Staff)",
            ]))
            ->sortBy('label')
            ->values();
        return view('attendance-logs.index', compact(['logs', 'schedules', 'people']));    
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'schedule_id' => 'required|exists:schedules,id',
            'attendance_type' => 'required|in:time_in,time_out',
            'scan_time' => 'required|date',
            'status_override' => 'nullable|in:present,late,absent,excused',
            'scanner_location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $validated['status'] = $this->resolveStatus($validated);
        unset($validated['status_override']);

        AttendanceLog::create($validated);

        return redirect()->route(['attendance-logs.index'])->with('success', 'Attendace log created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceLog $attendanceLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AttendanceLog $attendanceLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttendanceLog $attendanceLog)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'schedule_id' => 'required|exists:schedules,id',
            'attendance_type' => 'required|in:time_in,time_out',
            'scan_time' => 'required|date',
            'status_override' => 'nullable|in:present,late,absent,excused',
            'scanner_location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $validated['status'] = $this->resolveStatus($validated);
        unset($validated['status_override']);

        $attendanceLog->update($validated);

        return redirect()->route(['attendance-logs.index'])->with('success', 'Attendance log updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceLog $attendanceLog)
    {
        $attendanceLog->delete();

        return redirect()->route('attendance-logs.index')->with('success', 'Attendance log deleted successfully.');
    }

    // if admin picked an explicit override (excused, late, absent, etc..)

    private function resolveStatus(array $data): string
    {
        if(! empty($data['status_override'])) {
            return $data['status_override'];
        }

        if($data['attendance_type'] !== 'time_in') {
            return 'present';
        }

        $schedule = Schedule::findOrFail($data['schedule_id']);
        $scanTime = Carbon::parse($data['scan_time']);

        $scheduleStart = Carbon::parse(
            $scanTime->format('Y-m-d') . ' ' . $schedule->start_time
        )->addMinutes(self::GRACE_MINUTES);

        return $scanTime->greaterThan($scheduleStart) ? 'late' : 'present';
    }
}
