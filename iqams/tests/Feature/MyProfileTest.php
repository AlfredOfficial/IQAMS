<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_roles_can_view_my_profile_and_admin_cannot(): void
    {
        foreach (['student', 'instructor', 'staff'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(route('my-profile.edit'))
                ->assertOk()
                ->assertViewIs('my-profile.edit');
        }

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('my-profile.edit'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_my_profile(): void
    {
        $this->get('/my-profile')->assertRedirect(route('login'));
    }

    public function test_admin_profile_link_and_default_profile_route_remain_available(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertViewIs('profile.edit')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertDontSee('href="'.route('my-profile.edit').'"', false);
    }

    public function test_each_non_admin_role_sees_my_profile_link_and_is_redirected_from_profile(): void
    {
        foreach (['student', 'instructor', 'staff'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)
                ->get(route('my-profile.edit'))
                ->assertOk()
                ->assertSee('href="'.route('my-profile.edit').'"', false)
                ->assertDontSee('href="'.route('profile.edit').'"', false);

            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertRedirect(route('my-profile.edit'));
        }
    }

    public function test_user_updates_only_their_own_name_and_email(): void
    {
        $user = $this->userWithRole('student');
        $other = $this->userWithRole('student');

        $this->actingAs($user)
            ->patch(route('my-profile.update'), [
                'user_id' => $other->id,
                'name' => 'Updated Student',
                'email' => 'updated@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('my-profile.edit'));

        $this->assertSame('Updated Student', $user->refresh()->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNotSame('Updated Student', $other->refresh()->name);
    }

    public function test_current_password_is_required_and_must_match_before_password_change(): void
    {
        $user = $this->userWithRole('instructor');

        $this->actingAs($user)
            ->patch(route('my-profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'New-password-123',
                'password_confirmation' => 'New-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->patch(route('my-profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'password',
                'password' => 'New-password-123',
                'password_confirmation' => 'New-password-123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New-password-123', $user->refresh()->password));
    }

    public function test_replacing_an_avatar_deletes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old image');

        $user = $this->userWithRole('staff', ['avatar_path' => 'avatars/old.jpg']);

        $this->actingAs($user)
            ->patch(route('my-profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => UploadedFile::fake()->createWithContent(
                    'new-avatar.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                )->mimeType('image/png'),
            ])
            ->assertSessionHasNoErrors();

        $newPath = $user->refresh()->avatar_path;

        $this->assertNotNull($newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('avatars/old.jpg');
        $this->assertStringContainsString('/storage/'.$newPath, $user->avatar_url);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);

        return User::factory()->create(array_merge($attributes, ['role_id' => $role->id]));
    }
}
