<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_theme_preference(): void
    {
        $user = User::factory()->create(['theme' => 'system']);

        $this->actingAs($user)
            ->putJson(route('settings.appearance'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'dark',
        ]);
    }

    public function test_theme_preference_rejects_invalid_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('settings.appearance'), ['theme' => 'neon'])
            ->assertUnprocessable();
    }
}
