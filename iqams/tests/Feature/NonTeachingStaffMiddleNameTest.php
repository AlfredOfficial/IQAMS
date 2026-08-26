<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NonTeachingStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NonTeachingStaffMiddleNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_with_or_without_a_middle_name(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        Role::firstOrCreate(['role_name' => 'staff']);
        $department = $this->department();

        foreach ([
            ['employee_no' => 'STF-001', 'middle_name' => 'Santos', 'email' => 'with-middle@example.test'],
            ['employee_no' => 'STF-002', 'middle_name' => '', 'email' => 'without-middle@example.test'],
        ] as $data) {
            $this->actingAs($admin)->post(route('non-teaching-staff.store'), [
                'department_id' => $department->id,
                'employee_no' => $data['employee_no'],
                'first_name' => 'Jamie',
                'middle_name' => $data['middle_name'],
                'last_name' => 'Reyes',
                'email' => $data['email'],
                'avatar' => $this->avatar($data['employee_no'].'.png'),
            ])->assertRedirect(route('non-teaching-staff.index'))->assertSessionHasNoErrors();
        }

        $withMiddle = NonTeachingStaff::where('employee_no', 'STF-001')->firstOrFail();
        $withoutMiddle = NonTeachingStaff::where('employee_no', 'STF-002')->firstOrFail();

        $this->assertSame('Jamie Santos Reyes', $withMiddle->fullName());
        $this->assertSame('Jamie Santos Reyes', $withMiddle->user->name);
        $this->assertNull($withoutMiddle->middle_name);
        $this->assertSame('Jamie Reyes', $withoutMiddle->fullName());
        $this->assertSame('Jamie Reyes', $withoutMiddle->user->name);
    }

    public function test_admin_can_update_and_clear_a_staff_middle_name(): void
    {
        $admin = $this->user('admin');
        $staffUser = $this->user('staff');
        $department = $this->department();
        $staff = NonTeachingStaff::create([
            'user_id' => $staffUser->id,
            'department_id' => $department->id,
            'employee_no' => 'STF-003',
            'first_name' => 'Alex',
            'last_name' => 'Cruz',
            'qr_code' => 'STF-003',
        ]);

        $payload = [
            'department_id' => $department->id,
            'first_name' => 'Alex',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
        ];

        $this->actingAs($admin)->put(route('non-teaching-staff.update', $staff), $payload)
            ->assertRedirect(route('non-teaching-staff.index'))->assertSessionHasNoErrors();
        $this->assertSame('Alex Dela Cruz', $staff->fresh()->fullName());
        $this->assertSame('Alex Dela Cruz', $staffUser->fresh()->name);

        $payload['middle_name'] = '';
        $this->actingAs($admin)->put(route('non-teaching-staff.update', $staff), $payload)
            ->assertRedirect(route('non-teaching-staff.index'))->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->middle_name);
        $this->assertSame('Alex Cruz', $staff->fresh()->fullName());
        $this->assertSame('Alex Cruz', $staffUser->fresh()->name);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['role_name' => $role])->id,
            'status' => 'active',
        ]);
    }

    private function department(): Department
    {
        return Department::firstOrCreate(
            ['department_code' => 'ADMIN'],
            ['department_name' => 'Administrative Services'],
        );
    }

    private function avatar(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
