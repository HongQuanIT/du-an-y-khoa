<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\InstitutionMember;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Tests\TestCase;

final class ProfileBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_user_can_redeem_promo_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.redeem'), ['code' => 'medlearn2026'])
            ->assertRedirect(route('settings.edit', ['tab' => 'membership']))
            ->assertSessionHas('status');

        $this->assertTrue(
            Subscription::query()->where('user_id', $user->getKey())->where('source', 'redeem')->exists()
        );
        $this->assertTrue($user->fresh()->hasEntitlement(Entitlement::QbankFull->value));
        $this->assertTrue(
            Invoice::query()->where('user_id', $user->getKey())->exists()
        );
    }

    public function test_redeem_rejects_invalid_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.edit', ['tab' => 'redeem']))
            ->post(route('settings.redeem'), ['code' => 'INVALID'])
            ->assertRedirect(route('settings.edit', ['tab' => 'redeem']))
            ->assertSessionHasErrors('code');
    }

    public function test_user_can_activate_institution_license_by_email_domain(): void
    {
        $user = User::factory()->create(['email' => 'student@medlearn.local']);

        $this->actingAs($user)
            ->post(route('settings.org-license'), [
                'institution_email' => 'student@medlearn.local',
            ])
            ->assertRedirect(route('settings.edit', ['tab' => 'org-license']))
            ->assertSessionHas('status');

        $this->assertTrue(
            InstitutionMember::query()->where('user_id', $user->getKey())->where('status', 'verified')->exists()
        );
        $this->assertTrue(
            Subscription::query()->where('user_id', $user->getKey())->where('source', 'institution')->exists()
        );
        $this->assertTrue($user->fresh()->hasEntitlement(Entitlement::QbankFull->value));
    }

    public function test_user_can_save_account_notes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.notes'), [
                'account_notes' => 'Nhắc ôn tim mạch tuần này.',
            ])
            ->assertRedirect(route('settings.edit', ['tab' => 'notes']))
            ->assertSessionHas('status');

        $this->assertSame('Nhắc ôn tim mạch tuần này.', $user->fresh()->account_notes);
    }
}
