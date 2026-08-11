<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.avatar'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400)->size(800),
            ])
            ->assertRedirect(route('settings.edit', ['tab' => 'contact']))
            ->assertSessionHas('status');

        $user->refresh();
        $path = $user->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($user->avatarUrl());

        $this->actingAs($user)
            ->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.edit', ['tab' => 'contact']))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_upload_rejects_invalid_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.edit', ['tab' => 'contact']))
            ->put(route('settings.avatar'), [
                'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('settings.edit', ['tab' => 'contact']))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }
}
