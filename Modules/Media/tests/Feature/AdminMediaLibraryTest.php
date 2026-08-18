<?php

declare(strict_types=1);

namespace Modules\Media\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Models\CmsPage;
use Modules\Admin\Support\Cms\CmsPageDefaults;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Modules\Media\Models\Media;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;
use Tests\TestCase;

final class AdminMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_content_editor_can_open_library_and_upload_image(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertSee('Thư viện Media');

        $file = UploadedFile::fake()->image('hero.jpg', 640, 480);

        $this->actingAsStaff($editor)
            ->postJson(route('admin.media.store'), [
                'file' => $file,
                'alt' => 'Hero landing',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', MediaType::Image->value)
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.alt', 'Hero landing');

        $media = Media::query()->firstOrFail();
        $this->assertSame(MediaStatus::Ready, $media->status);
        $this->assertSame('public', $media->disk);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAsStaff($editor)
            ->get(route('admin.media.items'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $media->id);
    }

    public function test_cannot_delete_media_that_is_in_use(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $media = Media::factory()->create(['alt' => 'Đang dùng']);

        CmsPage::syncCatalog();
        $page = CmsPage::query()->where('key', CmsPageKey::Home->value)->firstOrFail();
        $content = CmsPageDefaults::for(CmsPageKey::Home);
        $content['hero']['image_media_id'] = $media->id;
        $content['hero']['image_url'] = $media->publicUrl() ?? $content['hero']['image_url'];

        $this->actingAsStaff($editor)
            ->put(route('admin.cms.pages.update', $page), [
                'title' => $page->title,
                'content' => $content,
                'action' => 'save',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media_usages', [
            'media_id' => $media->id,
            'usable_id' => $page->id,
        ]);

        $this->actingAsStaff($editor)
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect(route('admin.media.show', $media));

        $this->assertDatabaseHas('media', ['id' => $media->id, 'deleted_at' => null]);
    }

    public function test_student_cannot_access_media_admin(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.media.index'))
            ->assertForbidden();
    }

    public function test_update_metadata_requires_alt(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $media = Media::factory()->create(['alt' => 'Cũ']);

        $this->actingAsStaff($editor)
            ->put(route('admin.media.update', $media), [
                'alt' => '',
                'caption' => 'Chú thích',
            ])
            ->assertSessionHasErrors('alt');

        $this->actingAsStaff($editor)
            ->put(route('admin.media.update', $media), [
                'alt' => 'Ảnh hero trang chủ',
                'caption' => 'Landing',
                'credit' => 'Nội bộ',
            ])
            ->assertRedirect(route('admin.media.show', $media));

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'alt' => 'Ảnh hero trang chủ',
            'caption' => 'Landing',
        ]);
    }

    public function test_can_register_external_cdn_url_without_downloading(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $url = 'https://cdn.example.com/landing/hero.webp';

        $this->actingAsStaff($editor)
            ->postJson(route('admin.media.from-url'), [
                'url' => $url,
                'alt' => 'Hero CDN',
            ])
            ->assertCreated()
            ->assertJsonPath('data.external', true)
            ->assertJsonPath('data.url', $url)
            ->assertJsonPath('data.ready', true);

        $this->assertDatabaseHas('media', [
            'disk' => Media::DISK_EXTERNAL,
            'path' => $url,
            'alt' => 'Hero CDN',
        ]);

        $this->actingAsStaff($editor)
            ->postJson(route('admin.media.from-url'), [
                'url' => $url,
                'alt' => 'Hero CDN lần 2',
            ])
            ->assertCreated();

        $this->assertSame(1, Media::query()->where('path', $url)->count());
    }

    public function test_rejects_localhost_when_importing_url(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->postJson(route('admin.media.from-url'), [
                'url' => 'http://127.0.0.1/secret.png',
                'import' => true,
            ])
            ->assertUnprocessable();
    }

    public function test_imports_external_image_to_local_disk(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $url = 'https://example.com/photo.jpg';
        $tmp = UploadedFile::fake()->image('photo.jpg', 32, 32);
        $bytes = file_get_contents($tmp->getRealPath());
        $this->assertNotFalse($bytes);

        Http::fake([
            $url => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAsStaff($editor)
            ->postJson(route('admin.media.from-url'), [
                'url' => $url,
                'alt' => 'Ảnh import',
                'import' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.external', false)
            ->assertJsonPath('data.ready', true);

        $media = Media::query()->where('alt', 'Ảnh import')->firstOrFail();
        $this->assertSame('public', $media->disk);
        Storage::disk('public')->assertExists($media->path);
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
