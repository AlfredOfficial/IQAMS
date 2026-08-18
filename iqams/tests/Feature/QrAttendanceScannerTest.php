<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\QrAttendanceService;
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
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('attendance-logs.store'), ['user_id' => $user->id])
            ->assertSessionHasErrors(['user_id']);

        $this->assertDatabaseMissing('attendance_logs', ['user_id' => $user->id]);
    }

    public function test_active_user_can_be_given_manual_attendance(): void
    {
        $user = $this->createPersonnel('staff', 'EMP-MANUAL');
        $adminRole = Role::where('role_name', 'admin')->first() ?? Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($admin)->post(route('attendance-logs.store'), [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 08:00:00',
        ])->assertRedirect(route('attendance-logs.index'));

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'status' => 'present',
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_an_account(): void
    {
        [$user] = $this->studentWithSchedule();
        $adminRole = Role::where('role_name', 'admin')->first() ?? Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

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

        $missingUser = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active']);
        Student::create([
            'user_id' => $missingUser->id, 'student_no' => 'STU-002', 'first_name' => 'Missing',
            'last_name' => 'Student', 'qr_code' => 'STU-002', 'section_id' => $section->id,
            'course_id' => $course->id, 'status' => 'active',
        ]);

        $otherSection = Section::create([
            'course_id' => $course->id, 'section_name' => 'BSIT-4B',
            'school_year' => '2026-2027', 'semester' => '1st',
        ]);
        $otherUser = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active']);
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

    public function test_personnel_next_scan_must_be_inside_the_expected_stage_window(): void
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
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($admin)->get(route('attendance-scanner.index'))
            ->assertOk()
            ->assertSee('READY TO SCAN')
            ->assertSee('Dedicated scanner input')
            ->assertSee('Enter/CR is supported but not required')
            ->assertDontSee('too slow to be recognized')
            ->assertDontSee('getUserMedia')
            ->assertDontSee('BarcodeDetector')
            ->assertDontSee('<video', false);
        $this->post(route('logout'));

        $studentRole = Role::create(['role_name' => 'student']);
        $studentUser = User::factory()->create(['role_id' => $studentRole->id]);

        $this->actingAs($studentUser)->get(route('attendance-scanner.index'))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('attendance-scanner.index'))->assertRedirect(route('login'));
    }

    public function test_scanner_endpoint_returns_full_identity_and_recent_attendance(): void
    {
        [$studentUser] = $this->studentWithSchedule();
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila'));

        $this->actingAs($admin)
            ->postJson(route('attendance-scanner.store'), [
                'qr_code' => 'STU-001',
                'scanner_location' => 'T-D4 Main Desk',
            ])
            ->assertCreated()
            ->assertJsonPath('attendance.user_id', $studentUser->id)
            ->assertJsonPath('attendance.identifier', 'STU-001')
            ->assertJsonPath('attendance.name', $studentUser->name)
            ->assertJsonPath('attendance.role', 'Student')
            ->assertJsonPath('attendance.course_section', 'BSIT / BSIT-4A')
            ->assertJsonPath('attendance.subject', 'Capstone 2')
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonCount(1, 'recent_attendance');
    }

    public function test_scanner_endpoint_rejects_empty_and_invalid_qr_formats(): void
    {
        $adminRole = Role::create(['role_name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($admin)
            ->postJson(route('attendance-scanner.store'), ['qr_code' => ''])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['qr_code']]);

        $this->postJson(route('attendance-scanner.store'), ['qr_code' => "ABC\x07"])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['qr_code']]);
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

    private function studentWithSchedule(): array
    {
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'BSIT-4A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $studentRole = Role::create(['role_name' => 'student']);
        $user = User::factory()->create(['role_id' => $studentRole->id, 'status' => 'active']);
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
        $instructorUser = User::factory()->create(['role_id' => Role::create(['role_name' => 'instructor'])->id]);
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
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

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
            NonTeachingStaff::create([
                'user_id' => $user->id,
                'employee_no' => 'EMP-'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Staff',
                'qr_code' => $qrCode,
            ]);
        }

        return $user;
    }
}
