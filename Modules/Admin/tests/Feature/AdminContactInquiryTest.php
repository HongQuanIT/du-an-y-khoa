<?php

declare(strict_types=1);

namespace Modules\Admin\Tests\Feature;

use App\Models\User;
use App\Support\Auth\TwoFactorSession;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;
use Modules\Admin\Models\ContactInquiry;
use Modules\Auth\Models\TwoFactorSecret;
use Modules\Auth\Services\TotpService;
use Tests\TestCase;

final class AdminContactInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_list_and_view_contact_inquiries(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $inquiry = $this->makeInquiry();

        $this->actingAsStaff($admin)
            ->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertSee($inquiry->reference)
            ->assertSee('Hộp thư liên hệ');

        $this->actingAsStaff($admin)
            ->get(route('admin.contacts.show', $inquiry))
            ->assertOk()
            ->assertSee($inquiry->message)
            ->assertSee($inquiry->email);

        $this->assertNotNull($inquiry->fresh()->read_at);
    }

    public function test_admin_can_update_status_and_notes(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $inquiry = $this->makeInquiry();

        $this->actingAsStaff($admin)
            ->patch(route('admin.contacts.update', $inquiry), [
                'status' => ContactInquiryStatus::Resolved->value,
                'assigned_admin_id' => $admin->id,
                'admin_notes' => 'Đã gửi hướng dẫn reset mật khẩu.',
            ])
            ->assertRedirect(route('admin.contacts.show', $inquiry));

        $inquiry->refresh();

        $this->assertSame(ContactInquiryStatus::Resolved, $inquiry->status);
        $this->assertSame($admin->id, $inquiry->assigned_admin_id);
        $this->assertSame('Đã gửi hướng dẫn reset mật khẩu.', $inquiry->admin_notes);
        $this->assertNotNull($inquiry->resolved_at);
        $this->assertSame($admin->id, $inquiry->resolved_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.contact.update',
            'auditable_id' => (string) $inquiry->id,
        ]);
    }

    public function test_admin_can_claim_inquiry(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $inquiry = $this->makeInquiry();

        $this->actingAsStaff($admin)
            ->post(route('admin.contacts.claim', $inquiry))
            ->assertRedirect(route('admin.contacts.show', $inquiry));

        $inquiry->refresh();
        $this->assertSame(ContactInquiryStatus::InProgress, $inquiry->status);
        $this->assertSame($admin->id, $inquiry->assigned_admin_id);
    }

    public function test_content_editor_without_permission_cannot_access(): void
    {
        $editor = $this->staffUser(Role::ContentEditor);
        $inquiry = $this->makeInquiry();

        $this->assertFalse($editor->can(Permission::ContactView->value));

        $this->actingAsStaff($editor)
            ->get(route('admin.contacts.index'))
            ->assertForbidden();

        $this->actingAsStaff($editor)
            ->get(route('admin.contacts.show', $inquiry))
            ->assertForbidden();
    }

    public function test_sidebar_shows_contacts_for_admin(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $this->makeInquiry();

        $this->actingAsStaff($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Liên hệ');
    }

    private function makeInquiry(): ContactInquiry
    {
        return ContactInquiry::query()->create([
            'reference' => ContactInquiry::generateReference(),
            'name' => 'Trần Thị B',
            'email' => 'contact@example.com',
            'phone' => '0912345678',
            'subject' => ContactSubject::Payment,
            'message' => 'Tôi đã thanh toán nhưng gói chưa được kích hoạt.',
            'status' => ContactInquiryStatus::New,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
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
