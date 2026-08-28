<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingReturnController;
use Modules\Billing\Http\Controllers\BillingWebhookController;
use Modules\Billing\Http\Controllers\CheckoutController;
use Modules\Billing\Http\Controllers\SubscriptionController;

Route::post('/webhooks/billing/{provider}', BillingWebhookController::class)
    ->name('webhooks.billing');

Route::get('/billing/return/{gateway}', BillingReturnController::class)->name('billing.return');

Route::middleware('auth')->group(function (): void {
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::get('/subscription/upgrade', [CheckoutController::class, 'upgrade'])->name('subscription.upgrade');
    Route::get('/subscription/payment/{planPrice}', [CheckoutController::class, 'paymentMethods'])
        ->name('billing.payment-methods');

    Route::post('/billing/checkout', [CheckoutController::class, 'store'])->name('billing.checkout.store');
    Route::get('/billing/checkout/{uuid}', [CheckoutController::class, 'show'])->name('billing.checkout.show');
    Route::get('/billing/confirmation/{uuid}', [CheckoutController::class, 'confirmation'])->name('billing.confirmation');

    Route::get('/billing/fake-pay/{uuid}', [CheckoutController::class, 'showFakePay'])->name('billing.fake-pay.show');
    Route::post('/billing/fake-pay/{uuid}', [CheckoutController::class, 'completeFakePay'])->name('billing.fake-pay.complete');
});
