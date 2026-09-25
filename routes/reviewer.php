<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Reviewer\Http\Controllers\ReviewerDashboardController;
use Modules\Reviewer\Http\Controllers\ReviewerFlagController;
use Modules\Reviewer\Http\Controllers\ReviewerProfileController;

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'reviewer::auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'storeReviewer'])
        ->middleware('throttle:auth')->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroyReviewer'])
    ->middleware(['auth', 'portal:reviewer'])->name('logout');

Route::middleware(['auth', 'portal:reviewer'])->group(function (): void {
    Route::get('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'showReviewer'])->name('2fa.challenge');
    Route::post('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'verifyReviewer'])
        ->middleware('throttle:auth')->name('2fa.challenge.verify');

    Route::middleware('staff.2fa')->group(function (): void {
        Route::get('/', ReviewerDashboardController::class)->middleware('permission:reviewer_dashboard.view')->name('dashboard');
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('permission:reviewer_notification.view')
            ->name('notifications.index');
        Route::get('/questions/flags', [ReviewerFlagController::class, 'index'])
            ->middleware('permission:question_flag.view')->name('questions.flags.index');
        Route::get('/questions/flags/{question}', [ReviewerFlagController::class, 'show'])
            ->middleware('permission:question_flag.view')->name('questions.flags.show');
        Route::post('/questions/flags/{question}', [ReviewerFlagController::class, 'store'])
            ->middleware('permission:question.flag')->name('questions.flags.store');
        Route::put('/questions/flags/{question}', [ReviewerFlagController::class, 'update'])
            ->middleware('permission:question.flag')->name('questions.flags.update');
        Route::get('/profile', [ReviewerProfileController::class, 'show'])
            ->middleware('permission:profile.view')->name('profile.show');
        Route::put('/profile', [ReviewerProfileController::class, 'update'])
            ->middleware('permission:profile.update')->name('profile.update');
        Route::put('/profile/password', [ReviewerProfileController::class, 'updatePassword'])
            ->middleware('permission:profile.password_update')->name('profile.password');
        Route::put('/profile/appearance', [ReviewerProfileController::class, 'updateAppearance'])
            ->middleware('permission:profile.update')->name('profile.appearance');
        Route::put('/profile/avatar', [ReviewerProfileController::class, 'updateAvatar'])
            ->middleware('permission:profile.avatar_update')->name('profile.avatar');
        Route::delete('/profile/avatar', [ReviewerProfileController::class, 'destroyAvatar'])
            ->middleware('permission:profile.avatar_update')->name('profile.avatar.destroy');
        Route::middleware('permission:profile.two_factor_toggle')->group(function (): void {
            Route::get('/profile/2fa/setup', [ReviewerProfileController::class, 'showTwoFactorSetup'])->name('profile.2fa.setup');
            Route::post('/profile/2fa/confirm', [ReviewerProfileController::class, 'confirmTwoFactorSetup'])
                ->middleware('throttle:auth')->name('profile.2fa.confirm');
            Route::get('/profile/2fa/recovery', [ReviewerProfileController::class, 'showTwoFactorRecovery'])->name('profile.2fa.recovery');
            Route::post('/profile/2fa/recovery', [ReviewerProfileController::class, 'finishTwoFactorRecovery'])->name('profile.2fa.recovery.finish');
            Route::delete('/profile/2fa', [ReviewerProfileController::class, 'disableTwoFactor'])
                ->middleware('throttle:auth')->name('profile.2fa.disable');
        });
    });
});
