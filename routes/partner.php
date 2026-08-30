<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Partner\Http\Controllers\PartnerCodeController;
use Modules\Partner\Http\Controllers\PartnerCommissionController;
use Modules\Partner\Http\Controllers\PartnerDashboardController;
use Modules\Partner\Http\Controllers\PartnerPayoutController;
use Modules\Partner\Http\Controllers\PartnerReferralController;

/*
|--------------------------------------------------------------------------
| Partner portal (prefix `partner`, name `partner.`)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'partner::auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'storePartner'])
        ->middleware('throttle:auth')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroyPartner'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'partner'])->group(function (): void {
    Route::get('/', [PartnerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/codes', [PartnerCodeController::class, 'index'])->name('codes.index');

    Route::get('/referrals', [PartnerReferralController::class, 'index'])->name('referrals.index');
    Route::get('/commissions', [PartnerCommissionController::class, 'index'])->name('commissions.index');
    Route::get('/payouts', [PartnerPayoutController::class, 'index'])->name('payouts.index');
});
