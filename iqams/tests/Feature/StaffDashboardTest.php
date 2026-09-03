<?php

namespace Tests\Feature;

use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
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
        $officeUnit = OfficeUnit::where('code', 'REG')->firstOrFail();
        $user = User::factory()->create([
            'username' => 'STF001',
            'name' => 'Jamie Santos',
            'email' => 'jamie@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $user->assignRole($role);
        NonTeachingStaff::create([
            'user_id' => $user->id,
            'office_unit_id' => $officeUnit->id,
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
            ->assertSee('Registrar')
            ->assertSee('Non-Teaching Personnel')
            ->assertSee('0 of 4 completed')
            ->assertSee('0%')
            ->assertSee('Next:')
            ->assertSee('Morning In')
            ->assertSee('Lunch Out')
            ->assertSee('Afternoon In')
            ->assertSee('Final Out')
            ->assertSee('data-staff-progress-bar', false)
            ->assertSee('Staff ID: STF001')
            ->assertSee('Office/Unit: Registrar')
            ->assertDontSee('data-qr-value=', false)
            ->assertSee('data-id-card-url=', false)
            ->assertSee('id="staff-qr"', false)
            ->assertDontSee('View my QR code')
            ->assertDontSee('My Teaching Schedule');
    }

    public function test_staff_account_without_a_staff_profile_is_rejected(): void
    {
        $role = Role::create(['role_name' => 'staff']);
        $user = User::factory()->create([
            'username' => 'STF002',
            'name' => 'Unlinked Staff',
            'email' => 'unlinked@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $this->actingAs($user)->get(route('staff.dashboard'))->assertForbidden();
    }
}
