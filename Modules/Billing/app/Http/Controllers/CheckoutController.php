<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Billing\Actions\CreateCheckoutSessionAction;
use Modules\Billing\Actions\ListPublicPlansAction;
use Modules\Billing\Actions\ProcessPaymentWebhookAction;
use Modules\Billing\DTO\WebhookPayload;
use Modules\Billing\Models\CheckoutSession;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CheckoutIntent;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Billing\Support\GatewayResolver;
use Modules\Billing\Support\GatewaySettings;
use Modules\Billing\Support\MoneyFormatter;

final class CheckoutController extends Controller
{
    public function upgrade(Request $request, ListPublicPlansAction $list): View
    {
        $catalog = $list->handle();
        $premium = $catalog['premium'];
        $prices = $premium
            ? $premium->prices()->public()->ordered()->where('price_cents', '>', 0)->get()
            : collect();

        $selectedPlanPriceId = CheckoutIntent::resolveSelectedPriceId($request);
        if (
            $selectedPlanPriceId !== null
            && ! $prices->contains(fn ($price): bool => (int) $price->id === $selectedPlanPriceId)
        ) {
            $selectedPlanPriceId = null;
        }

        return view('billing::upgrade', [
            'current' => CurrentSubscription::for($request->user()),
            'premium' => $premium,
            'prices' => $prices,
            'selectedPlanPriceId' => $selectedPlanPriceId,
        ]);
    }

    public function paymentMethods(PlanPrice $planPrice, GatewaySettings $gatewaySettings): View
    {
        abort_unless($planPrice->is_public && $planPrice->price_cents > 0, 404);

        return view('billing::payment-methods', [
            'price' => $planPrice->load('plan'),
            'vnpayReady' => $gatewaySettings->isReady('vnpay'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, CreateCheckoutSessionAction $create, GatewayResolver $gateways): RedirectResponse
    {
        $data = $request->validate([
            'plan_price_id' => ['required', 'integer', 'exists:billing_plan_prices,id'],
            'gateway' => ['nullable', 'string', Rule::in($gateways->available())],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $session = $create->handle(
            user: $request->user(),
            planPriceId: (int) $data['plan_price_id'],
            idempotencyKey: $data['idempotency_key'] ?? $request->header('Idempotency-Key'),
            gateway: $data['gateway'] ?? null,
            buyerIp: $request->ip(),
        );

        if ($session->redirect_url) {
            return redirect()->away($session->redirect_url);
        }

        return redirect()->route('billing.checkout.show', $session->uuid);
    }

    public function show(Request $request, string $uuid): View|RedirectResponse
    {
        $session = $this->ownedSession($request, $uuid);

        if ($session->isCompleted()) {
            return redirect()->route('billing.confirmation', $session->uuid);
        }

        return view('billing::checkout', [
            'session' => $session->load(['planPrice.plan', 'invoice']),
        ]);
    }

    public function confirmation(Request $request, string $uuid): View
    {
        $session = $this->ownedSession($request, $uuid);

        return view('billing::confirmation', [
            'session' => $session->load(['planPrice.plan', 'invoice', 'payments']),
            'current' => CurrentSubscription::for($request->user()),
        ]);
    }

    public function showFakePay(Request $request, string $uuid): View
    {
        $session = $this->ownedSession($request, $uuid);
        abort_unless($session->gateway === 'fake', 404);

        return view('billing::fake-pay', [
            'session' => $session->load(['planPrice.plan']),
        ]);
    }

    public function completeFakePay(
        Request $request,
        string $uuid,
        ProcessPaymentWebhookAction $process,
    ): RedirectResponse {
        $session = $this->ownedSession($request, $uuid);
        abort_unless($session->gateway === 'fake', 404);

        $success = $request->boolean('success', true);

        $process->handle('fake', new WebhookPayload(
            eventId: $session->uuid.':'.($success ? 'ok' : 'fail').':'.time(),
            eventType: $success ? 'payment.succeeded' : 'payment.failed',
            orderId: $session->uuid,
            success: $success,
            amountCents: $session->totalCents(),
            providerPaymentId: 'fake-'.Str::random(8),
            raw: ['source' => 'fake-pay-ui'],
        ));

        if ($success) {
            return redirect()
                ->route('billing.confirmation', $session->uuid)
                ->with('status', 'Thanh toán thành công. Premium đã được kích hoạt.');
        }

        return redirect()
            ->route('billing.checkout.show', $session->uuid)
            ->with('error', 'Thanh toán thất bại. Vui lòng thử lại.');
    }

    private function ownedSession(Request $request, string $uuid): CheckoutSession
    {
        return CheckoutSession::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
