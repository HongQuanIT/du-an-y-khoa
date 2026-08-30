<?php

declare(strict_types=1);

namespace Modules\Landing\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Database\Seeders\CmsPageSeeder;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;
use Modules\Admin\Models\ContactInquiry;
use Modules\Notification\Models\UserNotification;
use Tests\TestCase;

final class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CmsPageSeeder::class);
    }

    public function test_guest_can_submit_contact_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $response = $this->from(route('landing.contact'))
            ->post(route('landing.contact.store'), $this->validPayload());

        $response->assertRedirect(route('landing.contact'))
            ->assertSessionHas('contact_success', true)
            ->assertSessionHas('contact_reference');

        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'guest@example.com',
            'subject' => ContactSubject::Account->value,
            'status' => ContactInquiryStatus::New->value,
            'user_id' => null,
        ]);

        $inquiry = ContactInquiry::query()->first();
        $this->assertNotNull($inquiry);
        $this->assertNotEmpty($inquiry->reference);
        $this->assertSame(session('contact_reference'), $inquiry->reference);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', 'contact.new')
                ->exists()
        );
    }

    public function test_authenticated_user_is_linked_on_submit(): void
    {
        $user = User::factory()->create([
            'name' => 'Học viên Test',
            'email' => 'learner@example.com',
        ]);
        $user->assignRole(Role::Student->value);

        $this->actingAs($user)
            ->post(route('landing.contact.store'), $this->validPayload([
                'name' => 'Học viên Test',
                'email' => 'learner@example.com',
            ]))
            ->assertRedirect(route('landing.contact'));

        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'learner@example.com',
            'user_id' => $user->id,
        ]);
    }

    public function test_validation_errors_are_returned(): void
    {
        $this->from(route('landing.contact'))
            ->post(route('landing.contact.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'subject' => 'invalid',
                'message' => 'short',
            ])
            ->assertRedirect(route('landing.contact'))
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message', 'privacy']);

        $this->assertDatabaseCount('contact_inquiries', 0);
    }

    public function test_honeypot_submission_is_silently_ignored(): void
    {
        $this->from(route('landing.contact'))
            ->post(route('landing.contact.store'), $this->validPayload([
                'company_website' => 'https://spam.example',
            ]))
            ->assertRedirect(route('landing.contact'))
            ->assertSessionHas('contact_success', true);

        $this->assertDatabaseCount('contact_inquiries', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Văn A',
            'email' => 'guest@example.com',
            'phone' => '0901234567',
            'subject' => ContactSubject::Account->value,
            'message' => 'Tôi cần hỗ trợ khôi phục tài khoản học viên của mình.',
            'privacy' => '1',
            'company_website' => '',
        ], $overrides);
    }
}
