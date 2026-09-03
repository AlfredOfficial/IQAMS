<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_integrity_report_finds_duplicate_attendance_and_reconciliation_supersedes_without_deleting(): void
    {
        $user = $this->user('staff');
        $first = $this->legacyAttendance($user, '2026-08-10 08:00:00', '2026-08-10 08:01:00');
        $second = $this->legacyAttendance($user, '2026-08-10 08:05:00', '2026-08-10 08:06:00');

        $this->artisan('integrity:report', ['--format' => 'json'])
            ->assertExitCode(0)
            ->expectsOutputToContain('attendance_duplicates');

        $manifest = $this->manifest([
            'attendance' => [[
                'ids' => [$first->id, $second->id],
                'canonical_id' => $second->id,
                'superseded_ids' => [$first->id],
            ]],
        ]);

        $this->artisan('integrity:reconcile', ['--apply' => true, '--manifest' => $manifest])
            ->assertExitCode(0);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $first->id,
            'record_state' => 'superseded',
            'superseded_by_id' => $second->id,
            'integrity_key' => null,
        ]);
        $this->assertNotNull(AttendanceLog::findOrFail($second->id)->integrity_key);
        $this->assertSame(2, AttendanceLog::whereKey([$first->id, $second->id])->count());

        $this->artisan('integrity:reconcile', ['--apply' => true, '--manifest' => $manifest])
            ->assertExitCode(0);
    }

    public function test_voiding_attendance_preserves_the_row_and_releases_its_integrity_key(): void
    {
        $user = $this->user('staff');
        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'scan_time' => '2026-08-10 08:00:00',
            'status' => 'present',
        ]);

        $log->update(['record_state' => 'voided']);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'record_state' => 'voided',
            'integrity_key' => null,
        ]);
    }

    public function test_new_leave_overlap_is_rejected_and_legacy_overlap_can_be_adjudicated(): void
    {
        $user = $this->user('instructor');
        $keep = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'reason' => 'First request',
        ]);
        $conflict = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'sick',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-14',
            'reason' => 'Legacy conflict',
        ]);

        $this->actingAs($user)->post(route('leave-requests.store'), [
            'leave_type' => 'emergency',
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-11',
            'reason' => 'New conflict',
        ])->assertSessionHasErrors('start_date');

        $manifest = $this->manifest([
            'leave' => [[
                'ids' => [$keep->id, $conflict->id],
                'keep_id' => $keep->id,
                'resolve_ids' => [$conflict->id],
            ]],
        ]);

        $this->artisan('integrity:reconcile', ['--apply' => true, '--manifest' => $manifest])
            ->assertExitCode(0);

        $this->assertSame('pending', $keep->fresh()->status);
        $this->assertSame('rejected', $conflict->fresh()->status);
        $this->assertNotNull($keep->fresh()->overlap_group_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.overlap_resolved']);
    }

    public function test_equal_schedule_times_are_rejected_but_cross_midnight_is_valid(): void
    {
        [$course, $section, $subject, $instructor] = $this->classroom();

        $this->expectException(ValidationException::class);
        Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '22:00',
            'end_time' => '22:00',
            'room' => 'Room 1',
        ]);
    }

    public function test_cross_midnight_schedule_can_be_stored_and_archived_without_losing_attendance_history(): void
    {
        [, $section, $subject, $instructor] = $this->classroom();
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '22:00',
            'end_time' => '01:00',
            'room' => 'Room 1',
        ]);
        $log = AttendanceLog::create([
            'user_id' => $this->user('student')->id,
            'schedule_id' => $schedule->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-10 23:00:00',
            'status' => 'present',
        ]);

        app(ArchiveService::class)->archive($schedule);

        $this->assertNotNull($schedule->fresh()->archived_at);
        $this->assertNotNull($log->fresh());
        $this->assertSame($schedule->id, $log->fresh()->schedule_id);
    }

    public function test_composite_student_relationship_rejects_a_section_from_another_course(): void
    {
        [$course, $section] = $this->classroom();
        $otherDepartment = Department::create(['department_code' => 'EDU', 'department_name' => 'Education']);
        $otherCourse = Course::create(['department_id' => $otherDepartment->id, 'course_code' => 'BSED', 'course_name' => 'Education']);
        $user = $this->user('student');

        $this->expectException(QueryException::class);
        Student::create([
            'user_id' => $user->id,
            'student_no' => 'STU-MISMATCH',
            'first_name' => 'Mismatch',
            'last_name' => 'Student',
            'section_id' => $section->id,
            'course_id' => $otherCourse->id,
            'status' => 'active',
        ]);
    }

    private function legacyAttendance(User $user, string $scanTime, string $updatedAt): AttendanceLog
    {
        $id = DB::table('attendance_logs')->insertGetId([
            'user_id' => $user->id,
            'schedule_id' => null,
            'school_event_id' => null,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'scan_time' => $scanTime,
            'scan_key' => null,
            'status' => 'present',
            'punctuality_status' => 'on_time',
            'scanner_location' => null,
            'remarks' => null,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        return AttendanceLog::findOrFail($id);
    }

    private function manifest(array $contents): string
    {
        $path = storage_path('framework/testing/integrity-'.uniqid().'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($contents, JSON_THROW_ON_ERROR));
        return $path;
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => 'active',
        ]);
    }

    private function classroom(): array
    {
        $department = Department::create(['department_code' => 'IT'.uniqid(), 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BS'.uniqid(), 'course_name' => 'Information Technology']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'IT'.uniqid(), 'subject_name' => 'Integrity', 'units' => 3]);
        $instructorUser = $this->user('instructor');
        $instructor = \App\Models\Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Instructor',
        ]);

        return [$course, $section, $subject, $instructor];
    }
}
