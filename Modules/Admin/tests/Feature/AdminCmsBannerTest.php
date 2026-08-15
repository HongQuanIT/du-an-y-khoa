<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminCmsBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_can_manage_banner(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.cms.banners.index'))
            ->assertOk()
            ->assertSee('Banner');

        $this->actingAsStaff($editor)
            ->post(route('admin.cms.banners.store'), [
                'title' => 'Banner khuyến mãi',
                'body' => 'Giảm 20% Premium tháng này.',
                'cta_label' => 'Xem giá',
                'cta_url' => '/pricing',
                'variant' => BannerVariant::Promo->value,
                'placement' => BannerPlacement::Landing->value,
                'audience' => BannerAudience::All->value,
                'is_dismissible' => '1',
                'sort_order' => 10,
                'action' => 'enable',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'title' => 'Banner khuyến mãi',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cms.banner.publish',
        ]);
    }

    public function test_enabled_landing_banner_appears_on_home(): void
    {
        Banner::query()->create([
            'title' => 'Landing live',
            'body' => 'Thông báo landing công khai CMS.',
            'variant' => BannerVariant::Info,
            'placement' => BannerPlacement::Landing,
            'audience' => BannerAudience::All,
            'is_enabled' => true,
            'is_dismissible' => true,
            'sort_order' => 10,
        ]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Thông báo landing công khai CMS.');
    }

    public function test_disabled_banner_is_hidden_from_home(): void
    {
        Banner::query()->create([
            'title' => 'Hidden',
            'body' => 'Banner tắt bí mật.',
            'variant' => BannerVariant::Info,
            'placement' => BannerPlacement::Landing,
            'audience' => BannerAudience::All,
            'is_enabled' => false,
            'sort_order' => 10,
        ]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertDontSee('Banner tắt bí mật.');
    }

    public function test_dashboard_banner_targets_free_students(): void
    {
        Banner::query()->create([
            'title' => 'Free only',
            'body' => 'Banner chỉ dành cho Free trên dashboard.',
            'cta_label' => 'Nâng cấp',
            'cta_url' => '/pricing',
            'variant' => BannerVariant::Info,
            'placement' => BannerPlacement::Dashboard,
            'audience' => BannerAudience::Free,
            'is_enabled' => true,
            'sort_order' => 10,
        ]);

        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Banner chỉ dành cho Free trên dashboard.');
    }

    public function test_expired_banner_is_not_shown(): void
    {
        Banner::query()->create([
            'title' => 'Expired',
            'body' => 'Banner đã hết hạn.',
            'variant' => BannerVariant::Warning,
            'placement' => BannerPlacement::Both,
            'audience' => BannerAudience::All,
            'is_enabled' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'sort_order' => 10,
        ]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertDontSee('Banner đã hết hạn.');
    }

    public function test_student_cannot_access_admin_banners(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.cms.banners.index'))
            ->assertForbidden();
    }

    public function test_toggle_disables_banner(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $banner = Banner::query()->create([
            'title' => 'Toggle me',
            'body' => 'Nội dung toggle.',
            'variant' => BannerVariant::Info,
            'placement' => BannerPlacement::Landing,
            'audience' => BannerAudience::All,
            'is_enabled' => true,
            'sort_order' => 10,
        ]);

        $this->actingAsStaff($editor)
            ->post(route('admin.cms.banners.toggle', $banner))
            ->assertRedirect();

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'is_enabled' => false,
        ]);
    }

    private function staffUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->enrollTwoFactor($user);

        return $user;
    }

    private function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession([
            TwoFactorSession::KEY => now()->timestamp,
        ]);
    }

    private function enrollTwoFactor(User $user): void
    {
        TwoFactorSecret::query()->create([
            'user_id' => $user->id,
            'secret' => (new TotpService)->generateSecret(),
            'recovery_codes' => [Hash::make('ABCD1234')],
            'confirmed_at' => now(),
        ]);
    }
}
