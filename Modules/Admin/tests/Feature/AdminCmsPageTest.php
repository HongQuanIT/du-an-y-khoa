<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageDefaults;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminCmsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
    }

    public function test_content_editor_can_update_static_page(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        CmsPage::syncCatalog();

        $page = CmsPage::query()->where('key', CmsPageKey::Terms->value)->firstOrFail();
        $content = CmsPageDefaults::for(CmsPageKey::Terms);
        $content['intro'] = 'Nội dung điều khoản mới.';

        $this->actingAsStaff($editor)
            ->get(route('admin.cms.pages.index'))
            ->assertOk()
            ->assertSee('Trang tĩnh')
            ->assertSee('Điều khoản sử dụng');

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.pages.update', $page), [
                'title' => 'Điều khoản sử dụng (cập nhật)',
                'content' => $content,
                'action' => 'publish',
            ])
            ->assertRedirect(route('admin.cms.pages.edit', $page));

        $this->assertDatabaseHas('cms_pages', [
            'key' => CmsPageKey::Terms->value,
            'title' => 'Điều khoản sử dụng (cập nhật)',
            'status' => CmsPageStatus::Published->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cms.page.publish',
        ]);
    }

    public function test_published_terms_page_is_public(): void
    {
        $content = CmsPageDefaults::for(CmsPageKey::Terms);
        $content['intro'] = 'Bạn đồng ý với các điều khoản này.';

        CmsPage::query()->create([
            'key' => CmsPageKey::Terms,
            'slug' => 'terms',
            'title' => 'Điều khoản sử dụng',
            'content' => $content,
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('landing.terms'))
            ->assertOk()
            ->assertSee('Điều khoản sử dụng')
            ->assertSee('Bạn đồng ý với các điều khoản này.');
    }

    public function test_draft_page_returns_404(): void
    {
        $content = CmsPageDefaults::for(CmsPageKey::Privacy);
        $content['intro'] = 'Bí mật CMS nháp.';

        CmsPage::query()->create([
            'key' => CmsPageKey::Privacy,
            'slug' => 'privacy',
            'title' => 'Chính sách bảo mật nháp',
            'content' => $content,
            'status' => CmsPageStatus::Draft,
        ]);

        $this->get(route('landing.privacy'))
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang', false);
    }

    public function test_unpublish_makes_public_url_404(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        CmsPage::syncCatalog();

        $page = CmsPage::query()->where('key', CmsPageKey::About->value)->firstOrFail();
        $page->update([
            'content' => CmsPageDefaults::for(CmsPageKey::About),
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('landing.about'))->assertOk();

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.pages.update', $page), [
                'title' => $page->title,
                'content' => CmsPageDefaults::for(CmsPageKey::About),
                'action' => 'unpublish',
            ])
            ->assertRedirect(route('admin.cms.pages.edit', $page));

        $this->assertDatabaseHas('cms_pages', [
            'key' => CmsPageKey::About->value,
            'status' => CmsPageStatus::Draft->value,
        ]);

        $this->get(route('landing.about'))->assertNotFound();
    }

    public function test_footer_links_to_terms_and_privacy(): void
    {
        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee(route('landing.terms'), false)
            ->assertSee(route('landing.privacy'), false);
    }

    public function test_landing_home_always_public_with_defaults_when_draft(): void
    {
        CmsPage::syncCatalog();

        $page = CmsPage::query()->where('key', CmsPageKey::Home->value)->firstOrFail();
        $this->assertFalse($page->isPublished());

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Học hiệu quả hơn', false)
            ->assertSee('12.450', false);
    }

    public function test_published_home_block_shows_cms_content(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        CmsPage::syncCatalog();

        $page = CmsPage::query()->where('key', CmsPageKey::Home->value)->firstOrFail();
        $content = CmsPageDefaults::for(CmsPageKey::Home);
        $content['hero']['badge'] = 'Badge CMS Home Test';

        $this->actingAsStaff($editor)
            ->get(route('admin.cms.pages.index', ['group' => 'landing']))
            ->assertOk()
            ->assertSee('Landing blocks')
            ->assertSee('Trang chủ (Landing)');

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.pages.update', $page), [
                'title' => 'Trang chủ CMS',
                'content' => $content,
                'action' => 'publish',
            ])
            ->assertRedirect(route('admin.cms.pages.edit', $page));

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Badge CMS Home Test', false);
    }

    public function test_unpublish_home_falls_back_to_defaults_not_404(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        CmsPage::syncCatalog();

        $page = CmsPage::query()->where('key', CmsPageKey::Home->value)->firstOrFail();
        $content = CmsPageDefaults::for(CmsPageKey::Home);
        $content['hero']['badge'] = 'Badge chỉ khi publish';

        $page->update([
            'content' => $content,
            'status' => CmsPageStatus::Published,
            'published_at' => now(),
        ]);

        $this->get(route('landing.home'))->assertOk()->assertSee('Badge chỉ khi publish', false);

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.pages.update', $page), [
                'title' => $page->title,
                'content' => $content,
                'action' => 'unpublish',
            ])
            ->assertRedirect(route('admin.cms.pages.edit', $page));

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertDontSee('Badge chỉ khi publish', false)
            ->assertSee('Hỗ trợ bởi AI dành riêng cho Y khoa', false);
    }

    public function test_features_page_renders_from_defaults(): void
    {
        CmsPage::syncCatalog();

        $this->get(route('landing.features'))
            ->assertOk()
            ->assertSee('Ngân hàng câu hỏi (QBank)', false)
            ->assertSee('AI Tutor Thông Minh', false);
    }

    public function test_student_cannot_access_admin_cms_pages(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.cms.pages.index'))
            ->assertForbidden();
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
