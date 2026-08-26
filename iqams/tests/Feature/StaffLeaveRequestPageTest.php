<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLeaveRequestPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_leave_page_uses_staff_layout_and_staff_routes(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)
            ->get(route('staff.leave-requests.index'))
            ->assertOk()
            ->assertViewIs('staff.leave-requests.index')
            ->assertSee('Staff Portal')
            ->assertSee(route('staff.leave-requests.store'), false)
            ->assertDontSee('Leave Request Review');
    }

    public function test_legacy_leave_page_redirects_staff_to_staff_page(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)
            ->get(route('leave-requests.index'))
            ->assertRedirectToRoute('staff.leave-requests.index');
    }

    public function test_admin_and_staff_leave_pages_remain_role_separated(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin');

        $this->actingAs($staff)
            ->get(route('admin.leave-requests.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.leave-requests.index'))
            ->assertOk()
            ->assertViewIs('admin.leave-requests.index')
            ->assertSee('Leave Request Review');

        $this->actingAs($admin)
            ->get(route('staff.leave-requests.index'))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
        ]);
    }
}
