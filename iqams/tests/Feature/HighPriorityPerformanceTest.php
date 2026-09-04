<?php

namespace Tests\Feature;

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
use App\Services\PersonnelAttendanceSummary;
use App\Services\StudentAbsenceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HighPriorityPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['app.timezone' => 'Asia/Manila']);
        Cache::flush();
    }

    public function test_personnel_dashboard_shares_one_monthly_attendance_query_and_then_uses_cache(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');
        $user = $this->staffUser();
        $queryCount = 0;

        DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
            if (str_contains(strtolower($query->sql), 'attendance_logs')) {
                $queryCount++;
            }
        });

        $first = app(PersonnelAttendanceSummary::class)->dashboardMonth($user, now('Asia/Manila'));
        $this->assertSame(1, $queryCount);
        $this->assertArrayHasKey('today', $first);
        $this->assertArrayHasKey('monthDays', $first);
        $this->assertArrayHasKey('totals', $first);

        $queryCount = 0;
        $second = app(PersonnelAttendanceSummary::class)->dashboardMonth($user, now('Asia/Manila'));

        $this->assertSame(0, $queryCount);
        $this->assertSame($first['totals'], $second['totals']);
    }

    public function test_admin_lookup_endpoints_return_bounded_searchable_results(): void
    {
        $admin = User::factory()->create(['role_id' => Role::findByName('admin', 'web')->id]);
        [$subject, $section, $instructor] = $this->academicFixture();
        $studentUser = User::factory()->create(['role_id' => Role::findByName('student', 'web')->id]);
        Student::create([
            'user_id' => $studentUser->id,
            'student_no' => 'STU-LOOKUP',
            'first_name' => 'Lookup',
            'last_name' => 'Student',
            'course_id' => $section->course_id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
        $schedule = Schedule::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Lookup Room',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.lookups.people', ['search' => 'Lookup', 'per_page' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $studentUser->id)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($admin)
            ->getJson(route('admin.lookups.schedules', ['search' => 'does-not-match', 'selected[]' => $schedule->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $schedule->id])
            ->assertJsonStructure(['data', 'meta' => ['has_more_pages']]);
    }

    public function test_notification_composite_index_is_present_and_reversible_by_name(): void
    {
        $indexes = collect(Schema::getIndexes('notifications'));

        $this->assertTrue($indexes->contains(fn (array $index): bool => $index['name'] === 'notifications_notifiable_type_id_type_created_at_index'));
    }

    public function test_absence_processing_uses_one_school_event_collection_for_multiple_occurrences(): void
    {
        Carbon::setTestNow('2026-08-10 10:00:00');
        [$subject, $section, $instructor] = $this->academicFixture();
        foreach (['08:00', '09:00'] as $start) {
            Schedule::create([
                'subject_id' => $subject->id,
                'instructor_id' => $instructor->id,
                'section_id' => $section->id,
                'day' => 'monday',
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i'),
                'room' => 'Absence Room',
            ]);
        }

        $schoolEventQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$schoolEventQueries): void {
            if (str_contains(strtolower($query->sql), 'school_events')) {
                $schoolEventQueries++;
            }
        });

        app(StudentAbsenceService::class)->markDue(now('Asia/Manila'));

        $this->assertSame(1, $schoolEventQueries);
    }

    private function staffUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::findByName('staff', 'web')->id]);
        NonTeachingStaff::create([
            'user_id' => $user->id,
            'employee_no' => 'STAFF-'.$user->id,
            'first_name' => 'Test',
            'last_name' => 'Staff',
        ]);

        return $user->fresh();
    }

    /** @return array{0: Subject, 1: Section, 2: Instructor} */
    private function academicFixture(): array
    {
        $department = Department::create([
            'department_code' => 'HP'.uniqid(),
            'department_name' => 'High Priority Department '.uniqid(),
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'course_code' => 'HP'.uniqid(),
            'course_name' => 'High Priority Course',
        ]);
        $section = Section::create([
            'course_id' => $course->id,
            'section_name' => 'HP-A',
            'school_year' => '2026-2027',
            'semester' => '1st',
        ]);
        $subject = Subject::create([
            'subject_code' => 'HP'.uniqid(),
            'subject_name' => 'High Priority Subject',
            'units' => 3,
        ]);
        $instructorUser = User::factory()->create(['role_id' => Role::findByName('instructor', 'web')->id]);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'department_id' => $department->id,
            'employee_no' => 'INS-'.$instructorUser->id,
            'first_name' => 'Test',
            'last_name' => 'Instructor',
        ]);

        return [$subject, $section, $instructor];
    }
}
