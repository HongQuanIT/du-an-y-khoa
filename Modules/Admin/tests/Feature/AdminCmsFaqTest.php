<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Enums\FaqCategory;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminCmsFaqTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_content_editor_can_manage_faq(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.cms.faq.index'))
            ->assertOk()
            ->assertSee('FAQ');

        $this->actingAsStaff($editor)
            ->post(route('admin.cms.faq.store'), [
                'category' => FaqCategory::TaiKhoan->value,
                'question' => 'Câu hỏi thử nghiệm?',
                'answer' => '<p>Đây là câu trả lời.</p>',
                'sort_order' => 10,
                'action' => 'publish',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faqs', [
            'question' => 'Câu hỏi thử nghiệm?',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cms.faq.publish',
        ]);
    }

    public function test_published_faq_appears_on_public_page(): void
    {
        Faq::query()->create([
            'category' => FaqCategory::GoiThanhToan,
            'question' => 'Premium có gì?',
            'answer' => '<p>Nhiều tính năng hơn gói Free.</p>',
            'sort_order' => 10,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('landing.faq'))
            ->assertOk()
            ->assertSee('Premium có gì?')
            ->assertSee('Nhiều tính năng hơn gói Free.');
    }

    public function test_draft_faq_is_hidden_from_public_page(): void
    {
        Faq::query()->create([
            'category' => FaqCategory::TaiKhoan,
            'question' => 'Câu hỏi nháp bí mật',
            'answer' => '<p>Không ai thấy.</p>',
            'sort_order' => 10,
            'is_published' => false,
        ]);

        $this->get(route('landing.faq'))
            ->assertOk()
            ->assertDontSee('Câu hỏi nháp bí mật');
    }

    public function test_student_cannot_access_admin_cms_faq(): void
    {
        $student = User::factory()->create();
        $student->assignRole(Role::Student->value);

        $this->actingAs($student)
            ->get(route('admin.cms.faq.index'))
            ->assertForbidden();
    }

    public function test_admin_can_delete_faq(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $faq = Faq::query()->create([
            'category' => FaqCategory::TaiKhoan,
            'question' => 'Xóa tôi',
            'answer' => '<p>Bye</p>',
            'sort_order' => 10,
            'is_published' => false,
        ]);

        $this->actingAsStaff($admin)
            ->delete(route('admin.cms.faq.destroy', $faq))
            ->assertRedirect(route('admin.cms.faq.index'));

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cms.faq.delete']);
    }

    public function test_cms_sidebar_route_is_available_for_content_editor(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);

        $this->actingAsStaff($editor)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('CMS');
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
