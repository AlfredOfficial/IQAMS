<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_initial_dashboard_keeps_the_full_contract_and_caps_scan_history(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $admin = $this->user('admin');
        $student = $this->user('student');

        foreach (range(1, 255) as $index) {
            AttendanceLog::create([
                'user_id' => $student->id,
                'attendance_type' => 'time_in',
                'scan_time' => now()->subSeconds($index),
                'status' => 'present',
            ]);
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee("url.searchParams.set('cursor', this.cursor)", false)
            ->assertSee('new Map(this.data.scans.map', false)
            ->assertSee('.slice(0, 250)', false)
            ->assertViewHas('dashboardData', function (array $data) {
                return count($data['scans']) === 250
                    && isset($data['stats'], $data['charts'], $data['overview'], $data['filters'], $data['generated_at'], $data['cursor']);
            });
    }

    public function test_realtime_cursor_returns_only_changed_rows_and_omits_static_filters(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $admin = $this->user('admin');
        $student = $this->user('student');
        $existing = AttendanceLog::create([
            'user_id' => $student->id,
            'attendance_type' => 'time_in',
            'scan_time' => now(),
            'status' => 'present',
        ]);
        $cursor = now()->toIso8601String();

        Carbon::setTestNow('2026-08-19 10:00:05');
        $existing->update(['status' => 'late']);
        $created = AttendanceLog::create([
            'user_id' => $student->id,
            'attendance_type' => 'time_out',
            'scan_time' => now(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.dashboard.realtime', ['cursor' => $cursor]))
            ->assertOk()
            ->assertJsonMissingPath('filters')
            ->assertJsonCount(2, 'scans');

        $this->assertEqualsCanonicalizing([$existing->id, $created->id], collect($response->json('scans'))->pluck('id')->all());
    }

    public function test_realtime_without_cursor_is_a_full_compatibility_response_and_invalid_cursor_is_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->getJson(route('admin.dashboard.realtime'))
            ->assertOk()->assertJsonStructure(['generated_at', 'cursor', 'stats', 'scans', 'charts', 'overview', 'filters']);

        $this->getJson(route('admin.dashboard.realtime', ['cursor' => now()->subMinute()->toIso8601String()]))
            ->assertOk()->assertJsonCount(0, 'scans');

        $this->getJson(route('admin.dashboard.realtime', ['cursor' => 'not-a-date']))
            ->assertUnprocessable()->assertJsonValidationErrors(['cursor']);
    }

    public function test_warm_incremental_dashboard_has_a_bounded_query_count(): void
    {
        app(AdminDashboardData::class)->build(includeFilters: true);
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $started = hrtime(true);
        app(AdminDashboardData::class)->build(now()->subSecond());
        $milliseconds = (hrtime(true) - $started) / 1_000_000;

        $this->assertLessThanOrEqual(20, $queries, "Incremental dashboard executed {$queries} queries.");
        $this->assertLessThan(1000, $milliseconds, "Incremental dashboard took {$milliseconds} ms in the test environment.");
    }

    public function test_current_presence_requires_a_present_or_late_time_in_as_the_latest_event(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $present = $this->user('student');
        $absent = $this->user('student');
        $timedOut = $this->user('staff');

        AttendanceLog::create([
            'user_id' => $present->id,
            'attendance_type' => 'time_in',
            'scan_time' => now()->subMinutes(5),
            'status' => 'late',
        ]);
        AttendanceLog::create([
            'user_id' => $absent->id,
            'attendance_type' => 'time_in',
            'scan_time' => now()->subMinutes(4),
            'status' => 'absent',
        ]);
        AttendanceLog::create([
            'user_id' => $timedOut->id,
            'attendance_type' => 'time_in',
            'scan_time' => now()->subMinutes(3),
            'status' => 'present',
        ]);
        AttendanceLog::create([
            'user_id' => $timedOut->id,
            'attendance_type' => 'time_out',
            'scan_time' => now()->subMinutes(2),
            'status' => 'present',
        ]);

        $this->assertSame(1, app(AdminDashboardData::class)->build()['stats']['present']);
    }

    private function user(string $role): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
            'status' => 'active',
        ]);

        return $user;
    }
}
