<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Billing\Actions\ExpireStaleCheckoutSessionsAction;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PlanPrice;
use Tests\TestCase;

final class ExpireStaleCheckoutSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDatabaseSeeder::class);
    }

    public function test_pending_past_expires_at_becomes_expired_with_payment_and_invoice(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-1y')->firstOrFail();

        $session = CheckoutSession::query()->create([
            'user_id' => $user->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'pending',
            'idempotency_key' => 'expire-me',
            'gateway' => 'vnpay',
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'checkout_session_id' => $session->id,
            'number' => 'INV-TEST-0001',
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'open',
            'description' => 'Test',
            'issued_at' => now(),
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'checkout_session_id' => $session->id,
            'amount_cents' => $price->price_cents,
            'currency' => 'VND',
            'method' => 'vnpay',
            'status' => 'pending',
            'provider' => 'vnpay',
        ]);

        $count = app(ExpireStaleCheckoutSessionsAction::class)->handle();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $session->fresh()->status);
        $this->assertSame('expired', Payment::query()->where('checkout_session_id', $session->id)->value('status'));
        $this->assertSame('void', $invoice->fresh()->status);
    }

    public function test_future_pending_sessions_are_not_expired(): void
    {
        $user = User::factory()->create();
        $price = PlanPrice::query()->where('slug', 'premium-monthly')->firstOrFail();

        CheckoutSession::query()->create([
            'user_id' => $user->id,
            'plan_price_id' => $price->id,
            'amount_cents' => $price->price_cents,
            'tax_cents' => 0,
            'discount_cents' => 0,
            'currency' => 'VND',
            'status' => 'pending',
            'idempotency_key' => 'still-valid',
            'gateway' => 'fake',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->assertSame(0, app(ExpireStaleCheckoutSessionsAction::class)->handle());
        $this->assertSame('pending', CheckoutSession::query()->where('idempotency_key', 'still-valid')->value('status'));
    }
}
