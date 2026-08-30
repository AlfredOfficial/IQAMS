<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_legacy_role_id_is_backed_by_exactly_one_spatie_role(): void
    {
        $role = Role::findByName('student', 'web');
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($user->fresh()->hasRole('student'));
        $this->assertCount(1, $user->fresh()->roles);
    }

    public function test_assignment_service_dual_writes_role_and_rejects_self_assignment(): void
    {
        $admin = $this->user('admin');
        $user = $this->user('student');

        app(RoleAssignmentService::class)->assign($user, 'staff', $admin);

        $this->assertTrue($user->fresh()->hasRole('staff'));
        $this->assertSame(Role::findByName('staff', 'web')->id, $user->fresh()->role_id);

        $this->expectException(ValidationException::class);
        app(RoleAssignmentService::class)->assign($admin, 'staff', $admin);
    }

    public function test_final_active_admin_cannot_be_reassigned(): void
    {
        $admin = $this->user('admin');

        $this->expectException(ValidationException::class);
        app(RoleAssignmentService::class)->assign($admin, 'student');
    }

    public function test_cross_dashboard_access_is_forbidden(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_inactive_account_cannot_login_or_use_a_dashboard(): void
    {
        $student = $this->user('student', 'inactive');

        $this->post('/login', ['user_id' => $student->username, 'password' => 'password'])
            ->assertSessionHasErrors('user_id');
        $this->assertGuest();

        $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function user(string $role, string $status = 'active'): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
            'status' => $status,
        ]);
    }
}
