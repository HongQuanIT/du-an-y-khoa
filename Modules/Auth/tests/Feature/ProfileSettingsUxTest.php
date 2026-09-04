<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use App\Support\TargetExams;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileSettingsUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

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
            ->assertRedirect(route('profile.show'));
    }

    public function test_admin_profile_hides_student_billing_notification_and_extra_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin);

        $nav = view('auth::partials.account-nav', ['active' => 'security'])->render();

        $this->assertStringContainsString('Bảo mật', $nav);
        $this->assertStringNotContainsString('Liên hệ', $nav);
        $this->assertStringNotContainsString('Thông báo', $nav);
        $this->assertStringNotContainsString('Gói &amp; giấy phép', $nav);
        $this->assertStringNotContainsString('Hóa đơn', $nav);
        $this->assertStringNotContainsString('Đổi mã', $nav);
        $this->assertStringNotContainsString('Giấy phép tổ chức', $nav);
        $this->assertStringNotContainsString('Ghi chú cá nhân', $nav);
    }

    public function test_student_profile_keeps_billing_notification_and_extra_tabs(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student);

        $nav = view('auth::partials.account-nav', ['active' => 'security'])->render();

        $this->assertStringContainsString('Thông báo', $nav);
        $this->assertStringContainsString('Gói &amp; giấy phép', $nav);
        $this->assertStringNotContainsString('Liên hệ', $nav);
        $this->assertStringContainsString('Hóa đơn', $nav);
        $this->assertStringContainsString('Đổi mã', $nav);
        $this->assertStringContainsString('Giấy phép tổ chức', $nav);
        $this->assertStringContainsString('Ghi chú cá nhân', $nav);
    }

    public function test_student_profile_uses_learner_layout_not_admin_badge(): void
    {
        $student = User::factory()->create(['name' => 'Quân Đặng']);
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Học viên', false)
            ->assertSee('Quân Đặng', false)
            ->assertDontSee('>Quản trị viên<', false)
            ->assertSee('Tổng quan')
            ->assertSee('Ngân hàng câu hỏi')
            ->assertDontSee('Quản trị hệ thống');
    }

    public function test_admin_profile_keeps_admin_shell(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Test']);
        $admin->assignRole(Role::Admin->value);

        $this->actingAs($admin)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Quản trị viên', false)
            ->assertSee('Admin Test', false)
            ->assertSee('Quản trị hệ thống')
            ->assertSee('Quản trị viên')
            ->assertDontSee('Ngân hàng câu hỏi');
    }
}
