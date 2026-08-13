<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\TargetExams;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileSettingsUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_objective_display_title_when_unset(): void
    {
        $this->assertSame('Chưa chọn', TargetExams::displayTitle(null));
        $this->assertSame('Chưa chọn', TargetExams::displayTitle(''));
        $this->assertSame('Chưa chọn', TargetExams::displayTitle('invalid-key'));
    }

    public function test_password_validation_keeps_security_tab(): void
    {
        $user = User::factory()->create(['password' => 'Password1!']);

        $this->actingAs($user)
            ->from(route('profile.show', ['tab' => 'security']))
            ->put(route('settings.password'), [
                'current_password' => 'wrong-password',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])
            ->assertRedirect(route('profile.show', ['tab' => 'security']))
            ->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_settings_sub_tabs_are_url_driven(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Đổi mật khẩu')
            ->assertDontSee('Tùy chọn thông báo');

        $this->actingAs($user)
            ->get(route('profile.show', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('Tùy chọn thông báo')
            ->assertDontSee('Đổi mật khẩu');
    }

    public function test_legacy_settings_url_redirects_to_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.edit', ['tab' => 'security']))
            ->assertRedirect(route('profile.show', ['tab' => 'security']));

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertRedirect(route('profile.show', ['tab' => 'contact']));
    }
}
