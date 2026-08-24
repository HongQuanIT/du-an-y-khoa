<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use App\Support\Enums\Entitlement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\DTO\WebhookPayload;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\WebhookEvent;
use Tests\TestCase;

final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);

        config(['billing.default_gateway' => 'fake']);
    }

    public function test_upgrade_page_lists_paid_skus(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('subscription.upgrade'))
            ->assertOk()
            ->assertSee('Nâng cấp Premium')
            ->assertSee('1 năm');
    }

    public function test_checkout_creates_session_and_redirects_to_fake_pay(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('billing.checkout.store'), [
                'plan_price_id' => $price->id,
                'gateway' => 'fake',
                'idempotency_key' => 'test-key-1',
            ]);

        $session = CheckoutSession::query()->where('idempotency_key', 'test-key-1')->first();
        $this->assertNotNull($session);
        $this->assertSame('pending', $session->status);

        $response->assertRedirect(route('billing.fake-pay.show', $session->uuid));
    }

    public function test_idempotent_checkout_returns_same_session(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();

        $this->actingAs($user)->post(route('billing.checkout.store'), [
            'plan_price_id' => $price->id,
            'gateway' => 'fake',
            'idempotency_key' => 'same-key',
        ]);

        $this->actingAs($user)->post(route('billing.checkout.store'), [
            'plan_price_id' => $price->id,
            'gateway' => 'fake',
            'idempotency_key' => 'same-key',
        ]);

        $this->assertSame(1, CheckoutSession::query()->where('idempotency_key', 'same-key')->count());
    }

    public function test_fake_pay_activates_subscription_and_entitlements(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $this->actingAs($user)->post(route('billing.checkout.store'), [
            'plan_price_id' => $price->id,
            'gateway' => 'fake',
            'idempotency_key' => 'activate-1',
        ]);

        $session = CheckoutSession::query()->where('idempotency_key', 'activate-1')->firstOrFail();

        $this->actingAs($user)
            ->post(route('billing.fake-pay.complete', $session->uuid), ['success' => '1'])
            ->assertRedirect(route('billing.confirmation', $session->uuid));

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertDatabaseHas('billing_payments', [
            'checkout_session_id' => $session->id,
            'status' => 'succeeded',
        ]);
        $this->assertTrue(
            Subscription::query()->where('user_id', $user->id)->active()->exists(),
        );
        $this->assertTrue($user->fresh()->hasEntitlement(Entitlement::QbankFull->value));
    }

    public function test_webhook_idempotency_does_not_double_charge(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();

        $this->actingAs($user)->post(route('billing.checkout.store'), [
            'plan_price_id' => $price->id,
            'gateway' => 'fake',
            'idempotency_key' => 'wh-1',
        ]);

        $session = CheckoutSession::query()->where('idempotency_key', 'wh-1')->firstOrFail();
        $payload = new WebhookPayload(
            eventId: 'evt-unique-1',
            eventType: 'payment.succeeded',
            orderId: $session->uuid,
            success: true,
            amountCents: $session->totalCents(),
            providerPaymentId: 'pay-1',
            raw: [],
        );

        $action = app(ProcessPaymentWebhookAction::class);
        $action->handle('fake', $payload);
        $action->handle('fake', $payload);

        $this->assertSame(1, Payment::query()->where('checkout_session_id', $session->id)->where('status', 'succeeded')->count());
        $this->assertSame(1, WebhookEvent::query()->where('event_id', 'evt-unique-1')->count());
        $this->assertSame('processed', WebhookEvent::query()->where('event_id', 'evt-unique-1')->value('status'));
    }

    public function test_api_checkout_requires_auth_and_returns_redirect(): void
    {
        $this->postJson(route('api.billing.subscription.checkout'), [
            'plan_price_id' => 1,
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'api-key-1')
            ->postJson(route('api.billing.subscription.checkout'), [
                'plan_price_id' => $price->id,
                'gateway' => 'fake',
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.gateway', 'fake')
            ->assertJsonStructure(['data' => ['attributes' => ['redirect_url']]]);
    }
}
