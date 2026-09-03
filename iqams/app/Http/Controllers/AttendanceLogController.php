<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountStatusService;
use App\Services\ApprovedLeaveAttendanceGuard;
use App\Services\AuditLogger;
use App\Services\AttendanceScheduleValidator;
use App\Services\PersonnelAttendanceClassifier;
use App\Services\StudentAttendanceWindow;
use App\ValueObjects\ScheduleOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AttendanceLog::canonical()->with(['user.nonTeachingStaff', 'schedule.subject', 'schedule.section', 'schoolEvent']);

        if ($request->filled('date')) {
            $query->whereDate('scan_time', $request->date('date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $logs = $query->latest('scan_time')->paginate(15)->withQueryString();

        $schedules = Schedule::active()->with(['subject', 'section'])->orderBy('day')->get();

        // build a combined list of loggable people
        // for the person dropdown each carrying their linked user_id

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
                'label' => $s->fullName().' (Staff)',
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
    public function store(
        Request $request,
        AttendanceScheduleValidator $scheduleValidator,
        AccountStatusService $accountStatus,
        ApprovedLeaveAttendanceGuard $leaveGuard,
        PersonnelAttendanceClassifier $personnelClassifier
    ) {
        $identity = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::with(['student', 'roles'])->findOrFail($identity['user_id']);
        $accountStatus->ensureAccountIsActive($user, 'user_id');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'schedule_id' => [Rule::requiredIf((bool) $user->student), 'nullable', 'exists:schedules,id'],
            'attendance_type' => 'required|in:time_in,time_out',
            'scan_time' => 'required|date',
            'status_override' => 'nullable|in:present,late,absent,excused',
            'scanner_location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $schedule = isset($validated['schedule_id']) ? Schedule::findOrFail($validated['schedule_id']) : null;
        $scanTime = Carbon::parse($validated['scan_time'], config('app.timezone'));
        $leaveGuard->ensureAttendanceIsAllowed($user, $scanTime, 'scan_time');

        $occurrence = null;
        if ($schedule) {
            $occurrence = $scheduleValidator->validate($user, $schedule, $scanTime);
        }

        if (! $user->student) {
            $classification = $personnelClassifier->classify($user->primaryRoleName(), $validated['attendance_type'], $scanTime);
            $this->ensurePersonnelPeriodIsUnique($user, $scanTime, $classification['attendance_period']);
            $validated = array_merge($validated, $classification);
        }

        $validated['status'] = $this->resolveStatus($validated, $occurrence, $scanTime, app(StudentAttendanceWindow::class));
        unset($validated['status_override']);

        $attendanceLog = AttendanceLog::create($validated);
        app(AuditLogger::class)->record('attendance.corrected', $attendanceLog, [
            'operation' => 'created',
        ], $request->user(), $request);

        return redirect()->route('attendance-logs.index')->with('success', 'Attendace log created successfully.');
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
    public function update(
        Request $request,
        AttendanceLog $attendanceLog,
        AttendanceScheduleValidator $scheduleValidator,
        AccountStatusService $accountStatus,
        ApprovedLeaveAttendanceGuard $leaveGuard,
        PersonnelAttendanceClassifier $personnelClassifier
    ) {
        $identity = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::with(['student', 'roles'])->findOrFail($identity['user_id']);
        $accountStatus->ensureAccountIsActive($user, 'user_id');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'schedule_id' => [Rule::requiredIf((bool) $user->student && ! $attendanceLog->school_event_id), 'nullable', 'exists:schedules,id'],
            'attendance_type' => 'required|in:time_in,time_out',
            'scan_time' => 'required|date',
            'status_override' => 'nullable|in:present,late,absent,excused',
            'scanner_location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        $schedule = isset($validated['schedule_id']) ? Schedule::findOrFail($validated['schedule_id']) : null;
        $scanTime = Carbon::parse($validated['scan_time'], config('app.timezone'));
        $leaveGuard->ensureAttendanceIsAllowed($user, $scanTime, 'scan_time');

        $occurrence = null;
        if ($schedule) {
            $occurrence = $scheduleValidator->validate($user, $schedule, $scanTime);
        }

        if (! $user->student) {
            $classification = $personnelClassifier->classify($user->primaryRoleName(), $validated['attendance_type'], $scanTime);
            $this->ensurePersonnelPeriodIsUnique($user, $scanTime, $classification['attendance_period'], $attendanceLog);
            $validated = array_merge($validated, $classification);
        } else {
            $validated['attendance_period'] = null;
            $validated['punctuality_status'] = null;
        }

        $validated['status'] = $this->resolveStatus($validated, $occurrence, $scanTime, app(StudentAttendanceWindow::class));
        unset($validated['status_override']);

        $attendanceLog->update($validated);
        app(AuditLogger::class)->record('attendance.corrected', $attendanceLog, [
            'operation' => 'updated',
        ], $request->user(), $request);

        return redirect()->route('attendance-logs.index')->with('success', 'Attendance log updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, AttendanceLog $attendanceLog)
    {
        $attendanceLog->update([
            'record_state' => 'voided',
            'superseded_by_id' => null,
        ]);
        app(AuditLogger::class)->record('record.voided', $attendanceLog, [
            'record' => 'attendance_log',
        ], $request->user(), $request);

        return redirect()->route('attendance-logs.index')->with('success', 'Attendance log voided successfully.');
    }

    // if admin picked an explicit override (excused, late, absent, etc..)

    private function resolveStatus(array $data, ?ScheduleOccurrence $occurrence, Carbon $scanTime, StudentAttendanceWindow $window): string
    {
        if (! empty($data['status_override'])) {
            return $data['status_override'];
        }

        if (! $occurrence && ($data['punctuality_status'] ?? null) === 'late') {
            return 'late';
        }

        if ($data['attendance_type'] !== 'time_in' || ! $occurrence) {
            return 'present';
        }

        return $window->status($occurrence, $scanTime);
    }

    private function ensurePersonnelPeriodIsUnique(User $user, Carbon $scanTime, string $period, ?AttendanceLog $except = null): void
    {
        $exists = AttendanceLog::canonical()->where('user_id', $user->id)
            ->whereDate('scan_time', $scanTime->toDateString())
            ->where('attendance_period', $period)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'scan_time' => str($period)->replace('_', ' ')->title().' has already been recorded for this date.',
            ]);
        }
    }
}
