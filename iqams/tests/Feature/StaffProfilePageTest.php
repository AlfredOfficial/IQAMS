<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_profile_uses_staff_layout_and_staff_routes(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)
            ->get(route('staff.profile.edit'))
            ->assertOk()
            ->assertViewIs('my-profile.edit')
            ->assertSee('Staff Portal')
            ->assertSee(route('staff.profile.update'), false)
            ->assertSee(route('staff.leave-requests.index'), false);
    }

    public function test_staff_dashboard_profile_links_use_the_staff_route(): void
    {
        $staff = $this->user('staff');

        $view = $this->actingAs($staff)->view('layouts.staff', [
            'title' => 'Dashboard',
            'slot' => '',
        ]);

        $view->assertSee(route('staff.profile.edit'), false)
            ->assertDontSee('href="'.route('my-profile.edit').'"', false);
    }

    public function test_non_staff_users_cannot_access_staff_profile_routes(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get(route('staff.profile.edit'))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
        ]);
    }
}
