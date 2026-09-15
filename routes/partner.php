<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'showPartner'])
        ->name('2fa.challenge');
    Route::post('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'verifyPartner'])
        ->name('2fa.challenge.verify');
});

Route::middleware(['auth', 'partner', 'partner.2fa', 'permission:partner.portal'])->group(function (): void {
    Route::get('/', [PartnerDashboardController::class, 'index'])
        ->middleware('permission:partner_dashboard.view')
        ->name('dashboard');

    Route::get('/codes', [PartnerCodeController::class, 'index'])
        ->middleware('permission:partner_code.view')
        ->name('codes.index');

    Route::get('/referrals', [PartnerReferralController::class, 'index'])
        ->middleware('permission:partner_referral.view')
        ->name('referrals.index');
    Route::get('/commissions', [PartnerCommissionController::class, 'index'])
        ->middleware('permission:partner_commission.view')
        ->name('commissions.index');
    Route::get('/payouts', [PartnerPayoutController::class, 'index'])
        ->middleware('permission:partner_payout.view')
        ->name('payouts.index');
});
