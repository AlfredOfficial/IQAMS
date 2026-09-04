<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_only_their_profile(): void
    {
        [$user, $student] = $this->studentAccount('2026-001');

        $this->actingAs($user)->get(route('student.profile'))
            ->assertOk()->assertSee($student->student_no)->assertSee($student->fullName());
    }

    public function test_student_can_update_contact_but_not_official_fields(): void
    {
        [$user, $student] = $this->studentAccount('2026-002');

        $this->actingAs($user)->patch(route('student.profile.contact'), [
            'email' => 'personal@example.com', 'contact_number' => '+63 912 345 6789',
            'student_no' => 'HACKED', 'first_name' => 'Changed', 'status' => 'graduated',
        ])->assertSessionHasNoErrors();

        $this->assertSame('personal@example.com', $user->refresh()->email);
        $this->assertSame('+63 912 345 6789', $student->refresh()->contact_number);
        $this->assertSame('2026-002', $student->student_no);
        $this->assertSame('Student', $student->first_name);
        $this->assertSame('active', $student->status);
    }

    public function test_legacy_profile_update_cannot_be_used_by_student(): void
    {
        [$user] = $this->studentAccount('2026-003');
        $this->actingAs($user)->patch(route('my-profile.update'), [
            'name' => 'Unauthorized', 'email' => 'unauthorized@example.com',
        ])->assertForbidden();
        $this->assertNotSame('Unauthorized', $user->refresh()->name);
    }

    public function test_student_can_replace_and_remove_photo(): void
    {
        Storage::fake('public');
        [$user] = $this->studentAccount('2026-004');
        $image = UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='))->mimeType('image/png');
        $this->actingAs($user)->put(route('student.profile.photo'), ['avatar' => $image])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($user->refresh()->avatar_path);
        $path = $user->avatar_path;
        $this->delete(route('student.profile.photo.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_uploaded_photo_has_bounded_primary_and_thumbnail_derivatives(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD is required to verify avatar resizing.');
        }

        Storage::fake('public');
        [$user] = $this->studentAccount('2026-004A');

        $source = imagecreatetruecolor(1200, 800);
        ob_start();
        imagejpeg($source, null, 95);
        $contents = ob_get_clean();
        imagedestroy($source);

        $image = UploadedFile::fake()->createWithContent('large-avatar.jpg', $contents)->mimeType('image/jpeg');

        $this->actingAs($user)->put(route('student.profile.photo'), ['avatar' => $image])->assertSessionHasNoErrors();

        $path = $user->refresh()->avatar_path;
        $thumbnailPath = \App\Services\ProfileImageService::thumbnailPath($path);
        $primarySize = getimagesizefromstring(Storage::disk('public')->get($path));
        $thumbnailSize = getimagesizefromstring(Storage::disk('public')->get($thumbnailPath));

        $this->assertLessThanOrEqual(512, max($primarySize[0], $primarySize[1]));
        $this->assertLessThanOrEqual(192, max($thumbnailSize[0], $thumbnailSize[1]));
        Storage::disk('public')->assertExists($thumbnailPath);
    }

    public function test_current_password_is_verified(): void
    {
        [$user] = $this->studentAccount('2026-005');
        $this->actingAs($user)->put(route('student.password'), [
            'current_password' => 'wrong', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123',
        ])->assertSessionHasErrors('current_password', errorBag: 'passwordUpdate');
        $this->put(route('student.password'), [
            'current_password' => 'password', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-password-123', $user->refresh()->password));
    }

    private function studentAccount(string $studentNo): array
    {
        $role = Role::firstOrCreate(['role_name' => 'student']);
        $department = Department::create(['department_code' => 'IT', 'department_name' => 'Information Technology']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'BS Information Technology']);
        $user = User::factory()->create(['role_id' => $role->id, 'name' => 'Student User']);
        $student = Student::create(['user_id' => $user->id, 'student_no' => $studentNo, 'first_name' => 'Student', 'last_name' => 'User', 'course_id' => $course->id, 'status' => 'active']);
        return [$user, $student];
    }
}
