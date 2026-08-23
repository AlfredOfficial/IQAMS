<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_member_can_view_the_staff_dashboard(): void
    {
        $role = Role::create(['role_name' => 'staff']);
        $department = Department::create([
            'department_code' => 'ADMIN',
            'department_name' => 'Administrative Services',
        ]);
        $user = User::create([
            'role_id' => $role->id,
            'username' => 'STF001',
            'name' => 'Jamie Santos',
            'email' => 'jamie@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        NonTeachingStaff::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'STF001',
            'first_name' => 'Jamie',
            'last_name' => 'Santos',
            'qr_code' => 'STF001',
        ]);

        $this->actingAs($user)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertViewIs('staff.dashboard')
            ->assertSee('Staff Portal')
            ->assertSeeText("Today's attendance")
            ->assertSee('Jamie Santos')
            ->assertSee('Administrative Services')
            ->assertSee('Non-Teaching Personnel')
            ->assertDontSee('My Teaching Schedule');
    }

    public function test_staff_account_without_a_staff_profile_is_rejected(): void
    {
        $role = Role::create(['role_name' => 'staff']);
        $user = User::create([
            'role_id' => $role->id,
            'username' => 'STF002',
            'name' => 'Unlinked Staff',
            'email' => 'unlinked@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('staff.dashboard'))->assertForbidden();
    }
}
