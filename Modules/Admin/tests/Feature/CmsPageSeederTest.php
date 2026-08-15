<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Database\Seeders\CmsPageSeeder;
use Modules\Admin\Support\Enums\CmsPageKey;
use Tests\TestCase;

final class CmsPageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_seeder_imports_about_and_contact_content(): void
    {
        $this->seed(CmsPageSeeder::class);

        $this->get(route('landing.about'))
            ->assertOk()
            ->assertSee('Sứ mệnh của')
            ->assertSee('Câu chuyện ra đời');

        $this->get(route('landing.contact'))
            ->assertOk()
            ->assertSee('Liên hệ với chúng tôi')
            ->assertSee('hotro@medpro.vn')
            ->assertSee('Gửi tin nhắn cho chúng tôi');
    }

    public function test_seeder_publishes_landing_home_and_features(): void
    {
        $this->seed(CmsPageSeeder::class);

        $this->get(route('landing.home'))
            ->assertOk()
            ->assertSee('Học hiệu quả hơn', false);

        $this->get(route('landing.features'))
            ->assertOk()
            ->assertSee('Ngân hàng câu hỏi (QBank)', false);
    }

    public function test_seeder_creates_all_catalog_pages(): void
    {
        $this->seed(CmsPageSeeder::class);

        foreach (CmsPageKey::cases() as $key) {
            $this->assertDatabaseHas('cms_pages', [
                'key' => $key->value,
                'status' => 'published',
            ]);
        }
    }
}
