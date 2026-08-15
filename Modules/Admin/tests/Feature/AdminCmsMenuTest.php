<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\Menu;
use Modules\Admin\Support\Cms\MenuDefaults;
use Modules\Admin\Support\Enums\MenuKey;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminCmsMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_can_update_header_menu(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        Menu::syncCatalog();
        $menu = Menu::findByKey(MenuKey::Header);
        $this->assertNotNull($menu);

        $this->actingAsStaff($editor)
            ->get(route('admin.cms.menus.index'))
            ->assertOk()
            ->assertSee('Menu điều hướng')
            ->assertSee('Menu header');

        $items = MenuDefaults::header();
        $items['links'][0]['label'] = 'Tính năng CMS';

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.menus.update', $menu), [
                'name' => 'Header nav',
                'items' => $items,
            ])
            ->assertRedirect(route('admin.cms.menus.edit', $menu));

        $this->assertDatabaseHas('menus', [
            'key' => MenuKey::Header->value,
            'name' => 'Header nav',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cms.menu.update',
        ]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Tính năng CMS', false);
    }

    public function test_disabled_header_link_is_hidden_on_public(): void
    {
        Menu::syncCatalog();
        $menu = Menu::findByKey(MenuKey::Header);
        $this->assertNotNull($menu);

        $items = MenuDefaults::header();
        foreach ($items['links'] as &$link) {
            if ($link['value'] === 'landing.faq') {
                $link['label'] = 'FAQ ẩn CMS';
                $link['enabled'] = false;
            }
        }
        unset($link);

        $menu->update(['items' => $items]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertDontSee('FAQ ẩn CMS', false)
            ->assertSee('Tính năng', false);
    }

    public function test_footer_menu_brand_and_links_render(): void
    {
        Menu::syncCatalog();
        $menu = Menu::findByKey(MenuKey::Footer);
        $this->assertNotNull($menu);

        $items = MenuDefaults::footer();
        $items['brand_blurb'] = 'Blurb footer CMS test.';
        $items['columns'][3]['links'][0]['label'] = 'Về chúng tôi CMS';

        $menu->update(['items' => $items]);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Blurb footer CMS test.', false)
            ->assertSee('Về chúng tôi CMS', false)
            ->assertSee(route('landing.terms'), false);
    }

    public function test_rejects_disallowed_route_name(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        Menu::syncCatalog();
        $menu = Menu::findByKey(MenuKey::Header);
        $this->assertNotNull($menu);

        $this->actingAsStaff($editor)
            ->from(route('admin.cms.menus.edit', $menu))
            ->put(route('admin.cms.menus.update', $menu), [
                'name' => 'Header',
                'items' => [
                    'links' => [
                        [
                            'label' => 'Hack',
                            'type' => 'route',
                            'value' => 'admin.dashboard',
                            'enabled' => true,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.cms.menus.edit', $menu))
            ->assertSessionHasErrors('items.links.0.value');
    }

    public function test_student_cannot_access_menus_admin(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.cms.menus.index'))
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
