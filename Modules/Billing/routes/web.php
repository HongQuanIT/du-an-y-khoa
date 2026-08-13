<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\SubscriptionController;

Route::middleware('auth')->group(function (): void {
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
});
