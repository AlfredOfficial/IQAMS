<?php

namespace Tests\Feature;

use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_manage_office_units(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('office-units.store'), [
            'code' => 'LEG', 'name' => 'Legal Affairs', 'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $unit = OfficeUnit::where('code', 'LEG')->firstOrFail();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put(route('office-units.update', $unit), [
            'code' => 'LEG', 'name' => 'Office of Legal Affairs', 'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($unit->fresh()->is_active);
    }

    public function test_referenced_office_unit_is_deactivated_instead_of_deleted(): void
    {
        $admin = $this->user('admin');
        $staffUser = $this->user('staff');
        $unit = OfficeUnit::where('code', 'LIB')->firstOrFail();
        NonTeachingStaff::create([
            'user_id' => $staffUser->id, 'office_unit_id' => $unit->id,
            'employee_no' => 'STF-LIB', 'first_name' => 'Library', 'last_name' => 'Staff',
        ]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->delete(route('office-units.destroy', $unit))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('office_units', ['id' => $unit->id, 'is_active' => 0]);
    }

    public function test_academic_department_id_is_not_accepted_as_staff_office_unit(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('non-teaching-staff.store'), [
            'office_unit_id' => 999999,
            'employee_no' => 'STF-BAD', 'first_name' => 'Bad', 'last_name' => 'Assignment',
            'email' => 'bad-office@example.test',
        ])->assertSessionHasErrors(['office_unit_id', 'avatar']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::findByName($role, 'web')->id]);
    }
}
