<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_sidebar_destinations_render_without_redirecting_back(): void
    {
        $this->actingAs($this->user('admin'));
        foreach ([
            'admin.dashboard', 'attendance-logs.index', 'instructors.index',
            'students.index', 'non-teaching-staff.index', 'schedules.index',
            'departments.index', 'courses.index', 'subjects.index', 'sections.index',
            'admin.leave-requests.index', 'school-events.index', 'office-units.index',
            'roles.index', 'scanner-security.index', 'admin.audit-logs.index',
        ] as $route) {
            $this->get(route($route))->assertOk()->assertSee('id="app-content"', false);
        }
    }

    public function test_mobile_and_desktop_notification_bells_share_two_queries_per_request(): void
    {
        $this->actingAs($this->user('admin'));
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'from "notifications"')) {
                $queries++;
            }
        });
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(2, $queries);
        $queries = 0;
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(2, $queries, 'Notification data must refresh on each new request.');
    }

    public function test_qr_user_lookup_is_bounded_searchable_and_excludes_ineligible_accounts(): void
    {
        $admin = $this->user('admin');
        for ($i = 0; $i < 30; $i++) {
            $this->user('student', 'Student '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }
        $match = $this->user('staff', 'Unique searchable person');
        $inactive = $this->user('instructor', 'Inactive person');
        $inactive->update(['status' => 'inactive']);

        $this->actingAs($admin)->getJson(route('scanner-security.users'))
            ->assertOk()->assertJsonCount(25, 'data');
        $this->getJson(route('scanner-security.users', ['search' => 'Unique searchable']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->getJson(route('scanner-security.users', ['search' => 'Inactive']))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('scanner-security.users', ['search' => $admin->name]))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->get(route('scanner-security.index'))->assertOk()->assertDontSee($match->name);
        $this->actingAs($match)->getJson(route('scanner-security.users'))->assertForbidden();
    }

    private function user(string $role, ?string $name = null): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'name' => $name ?? 'Navigation '.$role,
            'status' => 'active',
        ]);
    }
}
