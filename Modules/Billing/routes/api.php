<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\PlanController;

Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('subscription', [PlanController::class, 'subscription'])->name('subscription.show');
});
