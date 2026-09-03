<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4FunctionalCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_department_and_course_unsupported_resource_actions_are_not_exposed(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/departments/create')->assertStatus(405);
        $this->actingAs($admin)->get('/courses/create')->assertStatus(405);
        $this->actingAs($admin)->get('/departments/1')->assertStatus(405);
        $this->actingAs($admin)->get('/courses/1')->assertStatus(405);
    }

    public function test_department_modal_data_is_serialized_without_raw_script_markup(): void
    {
        $admin = $this->user('admin');
        Department::create([
            'department_code' => 'SEC',
            'department_name' => "</script><script>alert('x')</script>",
        ]);

        $response = $this->actingAs($admin)->get(route('departments.index'))->assertOk();

        $response->assertSee('JSON.parse', false)
            ->assertDontSee("</script><script>alert('x')</script>", false)
            ->assertDontSee('addslashes', false);
    }

    public function test_students_cannot_access_leave_request_pages(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)
            ->get(route('leave-requests.index'))
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::findByName($role, 'web')->id,
        ]);
    }
}
