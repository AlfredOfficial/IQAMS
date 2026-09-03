<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\ReportExport;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\PersonnelAttendancePages;
use App\Services\StudentAbsenceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase5PerformanceOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_absences_use_bulk_eligibility_queries_and_remain_idempotent(): void
    {
        [$schedule, $students] = $this->scheduledStudents(2);
        DB::enableQueryLog();

        $created = app(StudentAbsenceService::class)->markDue(Carbon::parse('2026-08-17 10:00:00', 'Asia/Manila'));
        $firstRunQueries = count(DB::getQueryLog());

        $this->assertSame(2, $created);
        $this->assertSame(2, $schedule->attendanceLogs()->where('status', 'absent')->count());
        $this->assertLessThanOrEqual(8, $firstRunQueries);

        $this->assertSame(0, app(StudentAbsenceService::class)->markDue(Carbon::parse('2026-08-17 10:00:00', 'Asia/Manila')));
        $this->assertCount(2, $students->filter(fn (Student $student) => $student->user->attendanceLogs()->where('status', 'absent')->exists()));
    }

    public function test_personnel_history_accepts_366_days_and_rejects_larger_or_reversed_ranges(): void
    {
        $user = $this->user('staff');
        $pages = app(PersonnelAttendancePages::class);

        $result = $pages->history($user, ['from' => '2025-09-01', 'to' => '2026-09-01']);
        $this->assertSame('2025-09-01', $result['from']->toDateString());
        $this->assertSame('2026-09-01', $result['to']->toDateString());

        try {
            $pages->history($user, ['from' => '2025-08-31', 'to' => '2026-09-01']);
            $this->fail('Expected the oversized report range to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $pages->history($user, ['from' => '2026-09-02', 'to' => '2026-09-01']);
    }

    public function test_leave_notifications_are_queued(): void
    {
        Queue::fake();
        $admin = $this->user('admin');
        $requester = $this->user('instructor');

        $this->actingAs($requester)->post(route('leave-requests.store'), [
            'leave_type' => 'sick',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'reason' => 'Medical rest required.',
        ])->assertRedirect();

        Queue::assertPushed(SendQueuedNotifications::class, 2);
        $this->assertDatabaseHas('leave_requests', ['user_id' => $requester->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_operations_health_passes_with_fresh_heartbeats_and_fails_when_stale(): void
    {
        Cache::put(config('operations.scheduler_heartbeat_key'), now()->timestamp, now()->addMinutes(5));
        Cache::put(config('operations.queue_heartbeat_key'), now()->timestamp, now()->addMinutes(5));

        $this->assertSame(0, Artisan::call('ops:health'));

        Cache::put(config('operations.queue_heartbeat_key'), now()->subSeconds(121)->timestamp, now()->addMinutes(5));

        $this->assertSame(1, Artisan::call('ops:health'));
    }

    public function test_expired_report_exports_are_removed_without_touching_attendance_data(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $export = ReportExport::create([
            'requested_by' => $admin->id,
            'report_type' => ReportExport::TYPE_DAILY_PERSONNEL,
            'format' => ReportExport::FORMAT_PDF,
            'parameters' => ['date' => '2026-09-01', 'filters' => []],
            'status' => ReportExport::STATUS_COMPLETED,
            'path' => 'report-exports/expired.pdf',
            'filename' => 'expired.pdf',
            'completed_at' => now()->subDay(),
            'expires_at' => now()->subSecond(),
        ]);
        Storage::disk('local')->put($export->path, 'temporary report');

        Artisan::call('reports:prune-exports');

        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing('report-exports/expired.pdf');
    }

    private function scheduledStudents(int $count): array
    {
        $department = Department::create(['department_code' => 'CCS', 'department_name' => 'Computer Studies']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSCS', 'course_name' => 'Computer Science']);
        $section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $subject = Subject::create(['subject_code' => 'CS101', 'subject_name' => 'Programming', 'units' => 3]);
        $instructor = $this->user('instructor');
        $profile = Instructor::create([
            'user_id' => $instructor->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-001',
            'first_name' => 'Test',
            'last_name' => 'Instructor',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $profile->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Room 1',
        ]);

        $students = collect(range(1, $count))->map(function (int $index) use ($course, $section): Student {
            $user = $this->user('student');

            return Student::create([
                'user_id' => $user->id,
                'student_no' => 'STU-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'first_name' => 'Student',
                'last_name' => (string) $index,
                'course_id' => $course->id,
                'section_id' => $section->id,
                'status' => 'active',
            ])->load('user');
        });

        return [$schedule, $students];
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
        ]);
    }
}
