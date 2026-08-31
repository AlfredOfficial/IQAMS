<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\ScannerTerminal;
use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\PersonnelAttendanceSummary;
use App\Services\QrAttendanceService;
use App\Services\SchoolEventAttendanceService;
use App\Services\StudentAbsenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QrAttendanceScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'Asia/Manila',
            'attendance.early_scan_minutes' => 15,
            'attendance.present_grace_minutes' => 15,
            'attendance.duplicate_cooldown_seconds' => 5,
            'attendance.personnel_daily_scan_limit' => 4,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_student_scan_automatically_matches_schedule_and_records_one_time_in(): void
    {
        [$user, $schedule] = $this->studentWithSchedule();

        $log = app(QrAttendanceService::class)->record(
            'STU-001',
            'Main scanner',
            Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'),
        );

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($schedule->id, $log->schedule_id);
        $this->assertSame('time_in', $log->attendance_type);
        $this->assertSame('present', $log->status);
        $this->assertSame('Main scanner', $log->scanner_location);
    }

    public function test_student_second_scan_for_the_same_class_date_is_rejected(): void
    {
        $this->studentWithSchedule();
        $service = app(QrAttendanceService::class);
        $service->record('STU-001', null, Carbon::parse('2026-08-10 08:05:00', 'Asia/Manila'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attendance already recorded for this subject.');

        $service->record('STU-001', null, Carbon::parse('2026-08-10 09:00:00', 'Asia/Manila'));
    }

    public function test_inactive_student_qr_scan_is_rejected_before_attendance_rules(): void
    {
        [$user] = $this->studentWithSchedule();
        $user->update(['status' => 'inactive']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attendance unavailable. Your account is inactive. Please contact the administrator.');

        app(QrAttendanceService::class)->record(
            'STU-001', null, Carbon::parse('2026-08-11 03:00:00', 'Asia/Manila'),
        );
    }

    public function test_inactive_user_cannot_be_given_manual_attendance(): void
    {
        [$user] = $this->studentWithSchedule();
        $user->update(['status' => 'inactive']);
        $adminRole = Role::where('role_name', 'admin')->first() ?? Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('attendance-logs.store'), ['user_id' => $user->id])
            ->assertSessionHasErrors(['user_id']);

        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $user->id]);
    }

    public function test_active_user_can_be_given_manual_attendance(): void
    {
        $user = $this->createPersonnel('staff', 'EMP-MANUAL');
        $adminRole = Role::where('role_name', 'admin')->first() ?? Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 08:00:00',
        ])->assertRedirect(route('attendance-logs.index'));

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'punctuality_status' => 'on_time',
            'status' => 'present',
        ]);
    }

    public function test_manual_instructor_attendance_uses_qr_period_and_punctuality_rules(): void
    {
        $user = $this->createPersonnel('instructor', 'INS-MANUAL');
        $admin = $this->createUser([
            'role_id' => Role::firstOrCreate(['role_name' => 'admin'])->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 08:05:00',
        ])->assertRedirect(route('attendance-logs.index'));

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'attendance_period' => 'morning_in',
            'punctuality_status' => 'late',
            'status' => 'late',
        ]);

        $day = app(PersonnelAttendanceSummary::class)->day(
            Carbon::parse('2026-08-10'),
            AttendanceLog::where('user_id', $user->id)->get(),
        );
        $this->assertNotNull($day['events']['morning_in']);
    }

    public function test_manual_personnel_duplicate_period_is_rejected(): void
    {
        $user = $this->createPersonnel('staff', 'STAFF-MANUAL-DUP');
        $admin = $this->createUser([
            'role_id' => Role::firstOrCreate(['role_name' => 'admin'])->id,
            'status' => 'active',
        ]);
        $payload = ['user_id' => $user->id, 'attendance_type' => 'time_in', 'scan_time' => '2026-08-10 08:00:00'];

        $this->actingAs($admin)->post(route('attendance-logs.store'), $payload)->assertRedirect();
        $this->post(route('attendance-logs.store'), $payload)->assertSessionHasErrors('scan_time');
        $this->assertSame(1, AttendanceLog::where('user_id', $user->id)->count());
    }

    public function test_staff_realtime_dashboard_returns_new_manual_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:05:00', 'Asia/Manila'));
        $user = $this->createPersonnel('staff', 'STAFF-REALTIME');
        AttendanceLog::create([
            'user_id' => $user->id, 'attendance_type' => 'time_in', 'attendance_period' => 'morning_in',
            'scan_time' => now(), 'status' => 'present', 'punctuality_status' => 'on_time',
        ]);

        $this->actingAs($user)->getJson(route('staff.dashboard.realtime'))
            ->assertOk()->assertJsonPath('today.events.morning_in.time', '8:05 AM')
            ->assertJsonPath('recent.0.label', 'Morning In');
    }

    public function test_student_realtime_dashboard_returns_subject_attendance(): void
    {
        [$user, $schedule] = $this->studentWithSchedule();
        AttendanceLog::create([
            'user_id' => $user->id, 'schedule_id' => $schedule->id,
            'attendance_type' => 'time_in', 'scan_time' => '2026-08-10 08:05:00', 'status' => 'present',
        ]);

        $this->actingAs($user)->getJson(route('student.dashboard.realtime'))
            ->assertOk()->assertJsonPath('stats.present', 1)
            ->assertJsonPath('recent.0.code', $schedule->subject->subject_code);
    }

    public function test_admin_can_deactivate_and_reactivate_an_account(): void
    {
        [$user] = $this->studentWithSchedule();
        $adminRole = Role::where('role_name', 'admin')->first() ?? Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)->patch(route('users.status.update', $user), ['status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $user->refresh()->status);

        $this->patch(route('users.status.update', $user), ['status' => 'active'])->assertRedirect();
        $this->assertSame('active', $user->refresh()->status);
    }

    public function test_student_scan_after_grace_period_is_late(): void
    {
        $this->studentWithSchedule();

        $log = app(QrAttendanceService::class)->record(
            'STU-001',
            null,
            Carbon::parse('2026-08-10 08:16:00', 'Asia/Manila'),
        );

        $this->assertSame('late', $log->status);
    }

    public function test_student_scan_without_a_current_schedule_is_rejected(): void
    {
        $this->studentWithSchedule();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You do not have a scheduled class at this time.');

        app(QrAttendanceService::class)->record(
            'STU-001',
            null,
            Carbon::parse('2026-08-11 08:10:00', 'Asia/Manila'),
        );
    }

    public function test_student_scan_before_dynamic_early_window_reports_opening_time(): void
    {
        $this->studentWithSchedule();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attendance scanning opens at 7:45 AM.');

        app(QrAttendanceService::class)->record(
            'STU-001',
            null,
            Carbon::parse('2026-08-10 07:44:00', 'Asia/Manila'),
        );
    }

    public function test_absence_process_marks_only_missing_students_in_the_scheduled_section(): void
    {
        [$presentUser, $schedule] = $this->studentWithSchedule();
        $section = $schedule->section;
        $course = $section->course;
        $studentRole = Role::where('role_name', 'student')->firstOrFail();

        $missingUser = $this->createUser(['role_id' => $studentRole->id, 'status' => 'active']);
        Student::create([
            'user_id' => $missingUser->id, 'student_no' => 'STU-002', 'first_name' => 'Missing',
            'last_name' => 'Student', 'qr_code' => 'STU-002', 'section_id' => $section->id,
            'course_id' => $course->id, 'status' => 'active',
        ]);

        $otherSection = Section::create([
            'course_id' => $course->id, 'section_name' => 'BSIT-4B',
            'school_year' => '2026-2027', 'semester' => '1st',
        ]);
        $otherUser = $this->createUser(['role_id' => $studentRole->id, 'status' => 'active']);
        Student::create([
            'user_id' => $otherUser->id, 'student_no' => 'STU-003', 'first_name' => 'Other',
            'last_name' => 'Section', 'qr_code' => 'STU-003', 'section_id' => $otherSection->id,
            'course_id' => $course->id, 'status' => 'active',
        ]);

        app(QrAttendanceService::class)->record(
            'STU-001', null, Carbon::parse('2026-08-10 08:00:00', 'Asia/Manila'),
        );

        $created = app(StudentAbsenceService::class)->markDue(
            Carbon::parse('2026-08-10 08:16:00', 'Asia/Manila'),
        );

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $presentUser->id, 'schedule_id' => $schedule->id, 'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $missingUser->id, 'schedule_id' => $schedule->id, 'status' => 'absent',
        ]);
        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $otherUser->id]);
    }

    public function test_late_scan_updates_automatic_absence_without_creating_a_duplicate(): void
    {
        [$user, $schedule] = $this->studentWithSchedule();
        app(StudentAbsenceService::class)->markDue(
            Carbon::parse('2026-08-10 08:16:00', 'Asia/Manila'),
        );

        $log = app(QrAttendanceService::class)->record(
            'STU-001', 'Main scanner', Carbon::parse('2026-08-10 08:20:00', 'Asia/Manila'),
        );

        $this->assertSame('late', $log->status);
        $this->assertSame('Main scanner', $log->scanner_location);
        $this->assertSame(1, AttendanceLog::where('user_id', $user->id)
            ->where('schedule_id', $schedule->id)->count());
    }

    public function test_multiple_same_day_schedules_select_the_subject_in_its_present_window(): void
    {
        [$user, $firstSchedule] = $this->studentWithSchedule();
        $secondSubject = Subject::create([
            'subject_code' => 'ENG1', 'subject_name' => 'English 1', 'units' => 3,
        ]);
        $secondSchedule = Schedule::create([
            'subject_id' => $secondSubject->id,
            'instructor_id' => $firstSchedule->instructor_id,
            'section_id' => $firstSchedule->section_id,
            'day' => 'monday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'room' => 'Room 2',
        ]);

        $log = app(QrAttendanceService::class)->record(
            'STU-001', null, Carbon::parse('2026-08-10 09:50:00', 'Asia/Manila'),
        );

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($secondSchedule->id, $log->schedule_id);
        $this->assertSame('present', $log->status);
    }

    public function test_duplicate_qr_across_profile_types_is_rejected_as_ambiguous(): void
    {
        $this->studentWithSchedule();
        $this->createPersonnel('instructor', 'STU-001');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('multiple profiles');

        app(QrAttendanceService::class)->record(
            'STU-001',
            null,
            Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'),
        );
    }

    public function test_personnel_scans_alternate_without_an_academic_schedule_and_stop_at_four(): void
    {
        $user = $this->createPersonnel('staff', 'EMP-001');
        $service = app(QrAttendanceService::class);
        $date = '2026-08-10 ';

        $logs = collect(['07:45:00', '12:00:00', '13:00:00', '17:00:00'])
            ->map(fn ($time) => $service->record('EMP-001', 'Gate', Carbon::parse($date.$time, 'Asia/Manila')));

        $this->assertSame(['time_in', 'time_out', 'time_in', 'time_out'], $logs->pluck('attendance_type')->all());
        $this->assertSame(['morning_in', 'lunch_out', 'afternoon_in', 'final_out'], $logs->pluck('attendance_period')->all());
        $this->assertTrue($logs->every(fn (AttendanceLog $log) => $log->schedule_id === null));
        $this->assertSame(4, AttendanceLog::where('user_id', $user->id)->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already been completed');

        $service->record('EMP-001', 'Gate', Carbon::parse($date.'18:00:00', 'Asia/Manila'));
    }

    public function test_instructor_can_record_afternoon_in_after_missing_lunch_out(): void
    {
        $user = $this->createPersonnel('instructor', 'INS-MISSED-LUNCH');
        $service = app(QrAttendanceService::class);

        $service->record('INS-MISSED-LUNCH', 'Gate', Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));
        $afternoon = $service->record('INS-MISSED-LUNCH', 'Gate', Carbon::parse('2026-08-10 13:00:00', 'Asia/Manila'));

        $this->assertSame('afternoon_in', $afternoon->attendance_period);
        $this->assertSame('time_in', $afternoon->attendance_type);
        $this->assertDatabaseMissing('attendance_logs', [
            'user_id' => $user->id,
            'attendance_period' => 'lunch_out',
        ]);
    }

    public function test_instructor_can_record_final_out_after_missing_earlier_periods(): void
    {
        $this->createPersonnel('instructor', 'INS-MISSED-PERIODS');
        $service = app(QrAttendanceService::class);

        $service->record('INS-MISSED-PERIODS', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));
        $final = $service->record('INS-MISSED-PERIODS', null, Carbon::parse('2026-08-10 17:00:00', 'Asia/Manila'));

        $this->assertSame('final_out', $final->attendance_period);
        $this->assertSame('time_out', $final->attendance_type);
    }

    public function test_instructor_shared_window_boundary_prefers_the_later_period(): void
    {
        $this->createPersonnel('instructor', 'INS-BOUNDARY');
        $service = app(QrAttendanceService::class);
        $service->record('INS-BOUNDARY', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));

        $log = $service->record('INS-BOUNDARY', null, Carbon::parse('2026-08-10 12:30:00', 'Asia/Manila'));

        $this->assertSame('afternoon_in', $log->attendance_period);
    }

    public function test_instructor_cannot_record_the_same_current_period_twice(): void
    {
        $this->createPersonnel('instructor', 'INS-DUPLICATE-PERIOD');
        $service = app(QrAttendanceService::class);
        $service->record('INS-DUPLICATE-PERIOD', null, Carbon::parse('2026-08-10 13:00:00', 'Asia/Manila'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Afternoon Time In has already been recorded');

        $service->record('INS-DUPLICATE-PERIOD', null, Carbon::parse('2026-08-10 13:10:00', 'Asia/Manila'));
    }

    public function test_instructor_summary_flags_a_skipped_period_and_advances_to_the_next_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 13:05:00', 'Asia/Manila'));
        $user = $this->createPersonnel('instructor', 'INS-SUMMARY-GAP');
        $service = app(QrAttendanceService::class);
        $service->record('INS-SUMMARY-GAP', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));
        $service->record('INS-SUMMARY-GAP', null, Carbon::parse('2026-08-10 13:00:00', 'Asia/Manila'));

        $logs = AttendanceLog::where('user_id', $user->id)->orderBy('scan_time')->get();
        $day = app(PersonnelAttendanceSummary::class)->day(today(), $logs);

        $this->assertFalse($day['isIncomplete']);
        $this->assertTrue($day['isInProgress']);
        $this->assertNull($day['events']['lunch_out']);
        $this->assertSame('In Progress', $day['status']);
        $this->assertSame('final_out', $day['nextPeriod']);
        $this->assertSame(0, $day['minutes']);
        $this->assertSame('In Progress', $day['punctuality']);
    }

    public function test_personnel_qr_scan_is_rejected_during_approved_leave(): void
    {
        $user = $this->createPersonnel('instructor', 'INS-LEAVE');
        LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => 'Approved leave.',
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('on approved leave');

        app(QrAttendanceService::class)->record(
            'INS-LEAVE', null, Carbon::parse('2026-08-11 07:45:00', 'Asia/Manila'),
        );
    }

    public function test_pending_rejected_future_and_expired_leave_do_not_block_personnel_scan(): void
    {
        $user = $this->createPersonnel('staff', 'EMP-LEAVE-RANGES');

        foreach ([
            ['status' => 'pending', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10'],
            ['status' => 'rejected', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10'],
            ['status' => 'approved', 'start_date' => '2026-08-09', 'end_date' => '2026-08-09'],
            ['status' => 'approved', 'start_date' => '2026-08-11', 'end_date' => '2026-08-11'],
        ] as $leave) {
            LeaveRequest::create($leave + [
                'user_id' => $user->id,
                'leave_type' => 'vacation',
                'reason' => 'Range test.',
            ]);
        }

        $log = app(QrAttendanceService::class)->record(
            'EMP-LEAVE-RANGES', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'),
        );

        $this->assertSame($user->id, $log->user_id);
    }

    public function test_manual_personnel_attendance_is_rejected_during_approved_leave(): void
    {
        $user = $this->createPersonnel('instructor', 'INS-MANUAL-LEAVE');
        LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'sick',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'reason' => 'Medical rest.',
            'status' => 'approved',
        ]);
        $adminRole = Role::firstOrCreate(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 08:00:00',
        ])->assertSessionHasErrors(['scan_time']);

        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $user->id]);
    }

    public function test_rapid_personnel_repeat_is_rejected(): void
    {
        $this->createPersonnel('staff', 'EMP-001');
        $service = app(QrAttendanceService::class);
        $service->record('EMP-001', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Please wait 5 seconds');

        $service->record('EMP-001', null, Carbon::parse('2026-08-10 07:45:03', 'Asia/Manila'));
    }

    public function test_teaching_personnel_cannot_time_in_before_the_morning_window(): void
    {
        $this->createPersonnel('instructor', 'INS-EARLY');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Morning Time In is only allowed from 6:30 AM to 8:30 AM');

        app(QrAttendanceService::class)->record(
            'INS-EARLY',
            null,
            Carbon::parse('2026-08-10 04:30:00', 'Asia/Manila'),
        );
    }

    public function test_instructor_scan_outside_a_window_reports_the_next_available_period(): void
    {
        $this->createPersonnel('instructor', 'INS-001');
        $service = app(QrAttendanceService::class);
        $service->record('INS-001', null, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Lunch Time Out is only allowed from 11:30 AM to 12:30 PM');

        $service->record('INS-001', null, Carbon::parse('2026-08-10 09:00:00', 'Asia/Manila'));
    }

    public function test_scanner_routes_require_an_admin(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id]);
        $this->actingAs($admin)->get(route('attendance-scanner.index'))
            ->assertOk()
            ->assertSee('IQAMS Attendance Terminal')
            ->assertSee('Continuous QR attendance kiosk')
            ->assertSee('Select this computer')
            ->assertDontSee('Confirm Attendance')
            ->assertDontSee('Dashboard')
            ->assertDontSee('getUserMedia')
            ->assertDontSee('BarcodeDetector')
            ->assertDontSee('<video', false);
        $this->post(route('logout'));

        $studentRole = Role::create(['role_name' => 'student']);
        $studentUser = $this->createUser(['role_id' => $studentRole->id]);

        $this->actingAs($studentUser)->get(route('attendance-scanner.index'))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('attendance-scanner.index'))->assertRedirect(route('login'));
    }

    public function test_scanner_scan_automatically_records_attendance_and_returns_display_data(): void
    {
        [$studentUser] = $this->studentWithSchedule();
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id]);
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'));
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'T-D4 Main Desk']);

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STU-001'])
            ->assertCreated()
            ->assertJsonPath('code', 'recorded')
            ->assertJsonPath('title', 'Attendance Recorded')
            ->assertJsonPath('person.id', $studentUser->id)
            ->assertJsonPath('person.identifier', 'STU-001')
            ->assertJsonPath('person.role', 'Student')
            ->assertJsonPath('person.department', 'Information Technology')
            ->assertJsonPath('person.details.0.label', 'Department')
            ->assertJsonPath('person.details.1.label', 'Course')
            ->assertJsonPath('person.details.1.value', 'BS Information Technology')
            ->assertJsonPath('person.details.2.label', 'Year Level')
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.display_time', '8:10 AM')
            ->assertJsonPath('attendance.recorded_time', 'Aug 10, 2026 8:10:00 AM');
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $studentUser->id, 'scanner_location' => 'T-D4 Main Desk']);
        $this->assertNotNull($terminal->fresh()->last_used_at);
    }

    public function test_scanner_scan_returns_schedule_error_without_recording_before_window_opens(): void
    {
        [$studentUser] = $this->studentWithSchedule();
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id]);
        Carbon::setTestNow(Carbon::parse('2026-08-10 07:30:00', 'Asia/Manila'));
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'T-D4 Main Desk']);

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STU-001'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'rejected')
            ->assertJsonPath('title', 'Attendance Not Recorded')
            ->assertJsonPath('person.id', $studentUser->id)
            ->assertJsonPath('message', 'Attendance scanning opens at 7:45 AM.');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_scanner_duplicate_returns_existing_attendance_time_without_creating_another_log(): void
    {
        [$studentUser] = $this->studentWithSchedule();
        $admin = $this->createUser(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'Main Gate']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'));

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STU-001'])
            ->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-08-10 08:12:00', 'Asia/Manila'));
        $this->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STU-001'])
            ->assertOk()
            ->assertJsonPath('code', 'already_recorded')
            ->assertJsonPath('title', 'Already Recorded')
            ->assertJsonPath('attendance.recorded_time', 'Aug 10, 2026 8:10:00 AM');

        $this->assertSame(1, AttendanceLog::where('user_id', $studentUser->id)->count());
    }

    public function test_scanner_inactive_account_returns_named_result_without_recording(): void
    {
        [$studentUser] = $this->studentWithSchedule();
        $studentUser->update(['status' => 'inactive']);
        $admin = $this->createUser(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'Main Gate']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'));

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STU-001'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'account_inactive')
            ->assertJsonPath('title', 'Account Inactive')
            ->assertJsonPath('person.id', $studentUser->id);

        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $studentUser->id]);
    }

    public function test_scanner_records_instructor_and_staff_consecutively(): void
    {
        $instructor = $this->createPersonnel('instructor', 'INS-KIOSK');
        $staff = $this->createPersonnel('staff', 'STAFF-KIOSK');
        $admin = $this->createUser(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'Main Gate']);
        Carbon::setTestNow(Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => 'INS-KIOSK'])
            ->assertCreated()
            ->assertJsonPath('code', 'recorded')
            ->assertJsonPath('person.id', $instructor->id)
            ->assertJsonPath('person.role', 'Instructor')
            ->assertJsonPath('person.department', 'General Department')
            ->assertJsonPath('person.details.1.label', 'Employee ID')
            ->assertJsonPath('person.details.2.value', 'Instructor');

        $this->postJson(route('attendance-scanner.scan'), ['qr_code' => 'STAFF-KIOSK'])
            ->assertCreated()
            ->assertJsonPath('code', 'recorded')
            ->assertJsonPath('person.id', $staff->id)
            ->assertJsonPath('person.role', 'Non-Teaching Staff')
            ->assertJsonPath('person.department', 'Registrar')
            ->assertJsonPath('person.details.0.label', 'Office/Unit')
            ->assertJsonPath('person.details.1.label', 'Employee ID')
            ->assertJsonPath('person.details.2.value', 'Non-Teaching Staff');

        $this->assertDatabaseHas('attendance_logs', ['user_id' => $instructor->id, 'attendance_period' => 'morning_in']);
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $staff->id, 'attendance_period' => 'morning_in']);
    }

    public function test_scanner_endpoint_rejects_empty_and_invalid_qr_formats(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = $this->createUser(['role_id' => $adminRole->id]);
        $terminal = ScannerTerminal::create(['name' => 'Main', 'location' => 'Main Gate']);

        $this->actingAs($admin)->withSession(['scanner_terminal_id' => $terminal->id])
            ->postJson(route('attendance-scanner.scan'), ['qr_code' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'rejected')
            ->assertJsonPath('title', 'Invalid QR Code');

        $this->postJson(route('attendance-scanner.scan'), ['qr_code' => "ABC\x07"])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'rejected');
    }

    public function test_qr_profile_role_mismatch_is_rejected(): void
    {
        [$user] = $this->studentWithSchedule();
        $user->update(['role_id' => Role::where('role_name', 'instructor')->value('id')]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('mismatched role');

        app(QrAttendanceService::class)->record(
            'STU-001',
            null,
            Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'),
        );
    }

    public function test_published_holiday_excuses_the_affected_class_instead_of_marking_absent(): void
    {
        [$user, $schedule] = $this->studentWithSchedule();
        $event = SchoolEvent::create([
            'title' => 'Foundation Day', 'starts_at' => '2026-08-10 07:00:00',
            'ends_at' => '2026-08-10 17:00:00', 'attendance_mode' => 'cancelled',
            'target_scope' => 'school', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->assertSame(1, app(StudentAbsenceService::class)->markDue(
            Carbon::parse('2026-08-10 08:16:00', 'Asia/Manila')));
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id, 'schedule_id' => $schedule->id,
            'school_event_id' => $event->id, 'status' => 'excused',
        ]);
    }

    public function test_one_required_event_scan_replaces_class_attendance(): void
    {
        [$user] = $this->studentWithSchedule();
        $event = SchoolEvent::create([
            'title' => 'General Assembly', 'starts_at' => '2026-08-10 08:00:00',
            'ends_at' => '2026-08-10 11:00:00', 'attendance_mode' => 'event_attendance',
            'target_scope' => 'school', 'status' => 'published', 'published_at' => now(),
        ]);

        $log = app(QrAttendanceService::class)->record('STU-001', 'Gym',
            Carbon::parse('2026-08-10 08:05:00', 'Asia/Manila'));

        $this->assertSame($event->id, $log->school_event_id);
        $this->assertNull($log->schedule_id);
        $this->assertSame('present', $log->status);
        $this->assertSame(1, AttendanceLog::where('user_id', $user->id)->count());
    }

    public function test_missing_required_event_creates_one_idempotent_event_absence(): void
    {
        [$user] = $this->studentWithSchedule();
        $event = SchoolEvent::create([
            'title' => 'General Assembly', 'starts_at' => '2026-08-10 08:00:00',
            'ends_at' => '2026-08-10 11:00:00', 'attendance_mode' => 'event_attendance',
            'target_scope' => 'school', 'status' => 'published', 'published_at' => now(),
        ]);
        $at = Carbon::parse('2026-08-10 11:01:00', 'Asia/Manila');

        $this->assertSame(1, app(SchoolEventAttendanceService::class)->markDue($at));
        $this->assertSame(0, app(SchoolEventAttendanceService::class)->markDue($at));
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id, 'school_event_id' => $event->id,
            'schedule_id' => null, 'status' => 'absent',
        ]);
    }

    public function test_draft_and_information_only_events_leave_class_scanning_unchanged(): void
    {
        [, $schedule] = $this->studentWithSchedule();
        SchoolEvent::create([
            'title' => 'Career Talk', 'starts_at' => '2026-08-10 08:00:00',
            'ends_at' => '2026-08-10 09:00:00', 'attendance_mode' => 'unchanged',
            'target_scope' => 'school', 'status' => 'published', 'published_at' => now(),
        ]);

        $log = app(QrAttendanceService::class)->record('STU-001', null,
            Carbon::parse('2026-08-10 08:05:00', 'Asia/Manila'));
        $this->assertSame($schedule->id, $log->schedule_id);
        $this->assertNull($log->school_event_id);
    }

    private function studentWithSchedule(): array
    {
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-4A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $studentRole = Role::create(['role_name' => 'student']);
        $user = $this->createUser(['role_id' => $studentRole->id, 'status' => 'active']);
        Student::create([
            'user_id' => $user->id,
            'student_no' => 'STU-001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'qr_code' => 'STU-001',
            'section_id' => $section->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $instructorUser = $this->createUser(['role_id' => Role::create(['role_name' => 'instructor'])->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-001',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'qr_code' => 'INS-001',
        ]);
        $subject = Subject::create(['subject_code' => 'CAP2', 'subject_name' => 'Capstone 2', 'units' => 3]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Lab 1',
        ]);

        return [$user, $schedule];
    }

    private function createPersonnel(string $roleName, string $qrCode): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $user = $this->createUser(['role_id' => $role->id, 'status' => 'active']);

        if ($roleName === 'instructor') {
            $department = Department::firstOrCreate(
                ['department_code' => 'GEN'],
                ['department_name' => 'General Department'],
            );
            Instructor::create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'employee_no' => 'INS-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Instructor',
                'qr_code' => $qrCode,
            ]);
        } else {
            $officeUnit = OfficeUnit::firstOrCreate(
                ['code' => 'REG'],
                ['name' => 'Registrar', 'is_active' => true],
            );
            NonTeachingStaff::create([
                'user_id' => $user->id,
                'office_unit_id' => $officeUnit->id,
                'employee_no' => 'EMP-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Staff',
                'qr_code' => $qrCode,
            ]);
        }

        return $user;
    }

    private function createUser(array $attributes): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }
}
