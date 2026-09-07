<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_thumbnail_url_does_not_prevent_saving_user(): void
    {
        $disk = Storage::fake('public');
        $disk->put('avatars/thumbs/photo.jpg', 'thumbnail');
        $user = User::factory()->create(['avatar_path' => 'avatars/photo.jpg']);

        $this->assertSame($disk->url('avatars/thumbs/photo.jpg'), $user->avatar_thumbnail_url);

        $user->name = 'Updated Name';
        $user->save();

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_thumbnail_url_tracks_avatar_changes_and_falls_back_to_original(): void
    {
        $disk = Storage::fake('public');
        $disk->put('avatars/thumbs/first.jpg', 'thumbnail');
        $user = new User(['avatar_path' => 'avatars/first.jpg']);

        $this->assertSame($disk->url('avatars/thumbs/first.jpg'), $user->avatar_thumbnail_url);

        $user->avatar_path = 'avatars/second.jpg';
        $this->assertSame($disk->url('avatars/second.jpg'), $user->avatar_thumbnail_url);

        $user->avatar_path = null;
        $this->assertNull($user->avatar_thumbnail_url);
    }
}
