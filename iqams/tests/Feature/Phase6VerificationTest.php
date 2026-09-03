<?php

namespace Tests\Feature;

use App\Exceptions\AttendanceAlreadyRecordedException;
use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdminAccountProtectionService;
use App\Services\PersonnelAttendanceReportService;
use App\Services\QrCredentialService;
use App\Services\QrIdentityResolver;
use App\Services\RoleAssignmentService;
use App\Services\StudentAbsenceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase6VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_portal_authorization_matrix_is_enforced_for_all_four_roles(): void
    {
        $users = $this->portalUsers();
        $matrix = [
            'admin.dashboard' => ['admin' => 200],
            'instructor.dashboard' => ['instructor' => 200],
            'student.dashboard' => ['student' => 200],
            'staff.dashboard' => ['staff' => 200],
            'leave-requests.index' => ['instructor' => 200, 'staff' => 302],
            'staff.leave-requests.index' => ['staff' => 200],
            'id-card.show' => ['instructor' => 200, 'staff' => 200, 'student' => 200],
            'attendance-scanner.index' => ['admin' => 200],
        ];

        foreach ($matrix as $route => $expectedStatuses) {
            foreach ($users as $role => $user) {
                $response = $this->actingAs($user)->get(route($route));

                if (isset($expectedStatuses[$role])) {
                    $response->assertStatus($expectedStatuses[$role]);
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_legacy_role_id_cannot_override_spatie_authorization(): void
    {
        $student = $this->user('student');
        $student->forceFill(['role_id' => Role::findByName('admin', 'web')->id])->saveQuietly();

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->assertSame('student', $student->fresh()->primaryRoleName());
    }

    public function test_final_admin_protection_covers_role_status_and_deletion_operations(): void
    {
        $admin = $this->user('admin');
        $protection = app(AdminAccountProtectionService::class);

        foreach ([
            fn () => app(RoleAssignmentService::class)->assign($admin, 'student'),
            fn () => $protection->assertCanChangeStatus($admin, 'inactive'),
            fn () => $protection->assertCanDelete($admin),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The final active administrator mutation was not rejected.');
            } catch (ValidationException) {
                // Expected: the invariant must be enforced inside the operation transaction.
            }
        }

        $this->assertSame(1, $protection->activeAdminCount());
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_revoked_qr_credentials_and_expired_legacy_qr_values_are_rejected(): void
    {
        $student = $this->user('student');
        $credentials = app(QrCredentialService::class);
        $oldCredential = $credentials->issue($student);
        $oldValue = $credentials->plainText($oldCredential);

        $credentials->regenerate($student);

        try {
            app(QrIdentityResolver::class)->resolve($oldValue);
            $this->fail('A revoked QR credential was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('revoked', $exception->errors()['qr_code'][0]);
        }

        [$legacyStudent, , $course] = $this->studentScheduleFixture();
        $legacyStudent->student->update(['qr_code' => 'LEGACY-CUTOFF-001']);
        config(['attendance.legacy_qr_cutoff' => now()->subSecond()->toDateTimeString()]);

        try {
            app(QrIdentityResolver::class)->resolve('LEGACY-CUTOFF-001');
            $this->fail('A legacy QR value past the cutoff was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no longer accepted', $exception->errors()['qr_code'][0]);
        }

        $this->assertNotNull($course);
    }

    public function test_overnight_occurrence_and_qr_attendance_use_the_same_session_boundary(): void
    {
        [$student, $schedule] = $this->studentScheduleFixture();
        $schedule->update(['start_time' => '22:00', 'end_time' => '01:00']);
        $scanAt = Carbon::parse('2026-08-11 00:30:00', 'Asia/Manila');

        $occurrence = app(\App\Services\ScheduleOccurrenceResolver::class)->resolveAt($schedule->fresh(), $scanAt);

        $this->assertNotNull($occurrence);
        $this->assertTrue($occurrence->overnight);
        $this->assertSame('2026-08-10', $occurrence->sessionDate->toDateString());
        $this->assertSame('2026-08-11 01:00:59', $occurrence->endsAt->toDateTimeString());

        $credential = app(QrCredentialService::class)->issue($student);
        $log = app(\App\Services\QrAttendanceService::class)->record(
            app(QrCredentialService::class)->plainText($credential),
            null,
            $scanAt,
        );

        $this->assertSame($schedule->id, $log->schedule_id);
        $this->assertSame('2026-08-11', $log->attendance_date?->toDateString());
    }

    public function test_duplicate_qr_scans_and_integrity_keys_protect_against_duplicate_inserts(): void
    {
        [$student, $schedule] = $this->studentScheduleFixture();
        $credentials = app(QrCredentialService::class);
        $credential = $credentials->issue($student);
        $scanAt = Carbon::parse('2026-08-10 08:10:00', 'Asia/Manila');

        app(\App\Services\QrAttendanceService::class)->record($credentials->plainText($credential), null, $scanAt);

        $this->expectException(AttendanceAlreadyRecordedException::class);
        app(\App\Services\QrAttendanceService::class)->record($credentials->plainText($credential), null, $scanAt);

        $this->assertSame(1, AttendanceLog::where('schedule_id', $schedule->id)->count());
    }

    public function test_database_integrity_key_rejects_a_duplicate_insert_that_would_result_from_concurrent_scans(): void
    {
        [$student, $schedule] = $this->studentScheduleFixture();
        $attributes = [
            'user_id' => $student->id,
            'schedule_id' => $schedule->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 08:10:00',
            'status' => 'present',
        ];

        AttendanceLog::create($attributes);

        try {
            AttendanceLog::create($attributes);
            $this->fail('The duplicate integrity key was accepted.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('attendance_logs', $exception->getMessage());
        }

        $this->assertSame(1, AttendanceLog::where('schedule_id', $schedule->id)->count());
    }

    public function test_overlapping_leave_submissions_are_rejected_without_rewriting_history(): void
    {
        $user = $this->user('instructor');
        $payload = [
            'leave_type' => 'vacation',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => 'Approved planning leave.',
        ];

        $this->actingAs($user)->post(route('leave-requests.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('leave-requests.store'), $payload + ['reason' => 'Conflicting request.'])
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, LeaveRequest::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $user->id,
            'reason' => 'Approved planning leave.',
            'status' => 'pending',
        ]);
    }

    public function test_private_report_exports_are_owner_only_and_expired_files_are_cleaned_up(): void
    {
        Storage::fake('local');
        $owner = $this->user('admin');
        $otherAdmin = $this->user('admin');
        $export = ReportExport::create([
            'requested_by' => $owner->id,
            'report_type' => ReportExport::TYPE_DAILY_PERSONNEL,
            'format' => ReportExport::FORMAT_PDF,
            'parameters' => ['date' => '2026-09-01', 'filters' => []],
            'status' => ReportExport::STATUS_COMPLETED,
            'path' => 'report-exports/private.pdf',
            'filename' => 'private.pdf',
            'completed_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->put($export->path, '%PDF-private');

        $this->actingAs($otherAdmin)
            ->getJson(route('admin.report-exports.show', $export))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.report-exports.download', $export))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $expired = ReportExport::create([
            'requested_by' => $owner->id,
            'report_type' => ReportExport::TYPE_DAILY_PERSONNEL,
            'format' => ReportExport::FORMAT_PDF,
            'parameters' => ['date' => '2026-09-01', 'filters' => []],
            'status' => ReportExport::STATUS_COMPLETED,
            'path' => 'report-exports/expired.pdf',
            'filename' => 'expired.pdf',
            'completed_at' => now()->subDay(),
            'expires_at' => now()->subSecond(),
        ]);
        Storage::disk('local')->put($expired->path, '%PDF-expired');

        $this->assertSame(0, Artisan::call('reports:prune-exports'));
        $this->assertDatabaseMissing('report_exports', ['id' => $expired->id]);
        Storage::disk('local')->assertMissing('report-exports/expired.pdf');
        $this->assertDatabaseHas('report_exports', ['id' => $export->id]);
    }

    public function test_integrity_migration_rollback_hooks_preserve_populated_historical_rows(): void
    {
        $user = $this->user('staff');
        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'scan_time' => '2026-08-10 08:00:00',
            'status' => 'present',
        ]);

        foreach ([
            '2026_09_02_000000_add_integrity_staging_fields.php',
            '2026_09_02_000001_add_integrity_constraints.php',
        ] as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->down();
        }

        $this->assertDatabaseHas('attendance_logs', ['id' => $log->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_absence_generation_remains_set_based_for_a_larger_student_group(): void
    {
        [$schedule] = $this->scheduledStudents(25);
        DB::enableQueryLog();

        $created = app(StudentAbsenceService::class)->markDue(
            Carbon::parse('2026-08-10 10:00:00', 'Asia/Manila'),
        );
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(25, $created);
        $this->assertSame(25, AttendanceLog::where('schedule_id', $schedule->id)->where('status', 'absent')->count());
        $this->assertLessThanOrEqual(8, $queries, "Absence generation executed {$queries} queries.");
    }

    public function test_personnel_export_keeps_large_result_sets_bounded_to_requested_personnel(): void
    {
        $this->user('admin');
        $office = OfficeUnit::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);

        foreach (range(1, 40) as $index) {
            $user = $this->user('staff');
            NonTeachingStaff::create([
                'user_id' => $user->id,
                'office_unit_id' => $office->id,
                'employee_no' => 'EMP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Staff',
                'last_name' => (string) $index,
            ]);
        }

        $report = app(PersonnelAttendanceReportService::class)->getDailyReport(
            Carbon::parse('2026-09-01', 'Asia/Manila'),
            ['office_unit_id' => $office->id],
        );

        $this->assertCount(40, $report['rows']);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => 'active',
        ]);
    }

    /** @return array<string, User> */
    private function portalUsers(): array
    {
        $department = Department::create(['department_code' => 'PORTAL', 'department_name' => 'Portal Department']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'PORTAL', 'course_name' => 'Portal Course']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $office = OfficeUnit::create(['code' => 'PORTAL', 'name' => 'Portal Office', 'is_active' => true]);

        $admin = $this->user('admin');
        $instructor = $this->user('instructor');
        Instructor::create([
            'user_id' => $instructor->id,
            'department_id' => $department->id,
            'employee_no' => 'PORTAL-INS',
            'first_name' => 'Portal',
            'last_name' => 'Instructor',
        ]);

        $student = $this->user('student');
        Student::create([
            'user_id' => $student->id,
            'student_no' => 'PORTAL-STU',
            'first_name' => 'Portal',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        $staff = $this->user('staff');
        NonTeachingStaff::create([
            'user_id' => $staff->id,
            'office_unit_id' => $office->id,
            'employee_no' => 'PORTAL-STAFF',
            'first_name' => 'Portal',
            'last_name' => 'Staff',
        ]);

        return compact('admin', 'instructor', 'student', 'staff');
    }

    /** @return array{0: User, 1: Schedule, 2: Course} */
    private function studentScheduleFixture(): array
    {
        $department = Department::create(['department_code' => 'ATT', 'department_name' => 'Attendance']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSA', 'course_name' => 'Attendance']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'ATT101', 'subject_name' => 'Attendance', 'units' => 3]);
        $instructorUser = $this->user('instructor');
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'ATT-INS',
            'first_name' => 'Attendance',
            'last_name' => 'Instructor',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Room 1',
        ]);
        $student = $this->user('student');
        Student::create([
            'user_id' => $student->id,
            'student_no' => 'ATT-STU-'.uniqid(),
            'first_name' => 'Attendance',
            'last_name' => 'Student',
            'course_id' => $course->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        return [$student->fresh(), $schedule->fresh(), $course];
    }

    /** @return array{0: Schedule, 1: array<int, Student>} */
    private function scheduledStudents(int $count): array
    {
        $department = Department::create(['department_code' => 'BULK', 'department_name' => 'Bulk Attendance']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BULK', 'course_name' => 'Bulk Attendance']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'BULK101', 'subject_name' => 'Bulk Attendance', 'units' => 3]);
        $instructorUser = $this->user('instructor');
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'BULK-INS',
            'first_name' => 'Bulk',
            'last_name' => 'Instructor',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Room 1',
        ]);
        $students = [];

        foreach (range(1, $count) as $index) {
            $user = $this->user('student');
            $students[] = Student::create([
                'user_id' => $user->id,
                'student_no' => 'BULK-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Bulk',
                'last_name' => (string) $index,
                'course_id' => $course->id,
                'section_id' => $section->id,
                'status' => 'active',
            ]);
        }

        return [$schedule, $students];
    }
}
