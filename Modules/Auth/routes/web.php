<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PasswordResetController;
use Modules\Auth\Http\Controllers\PasswordResetLinkController;
use Modules\Auth\Http\Controllers\ProfileController;
use Modules\Auth\Http\Controllers\RegisteredUserController;
use Modules\Auth\Http\Controllers\SettingsTwoFactorController;
use Modules\Auth\Http\Controllers\StudentTwoFactorController;

/*
| Auth — web routes (login/register/password screens).
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth::login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth');

    Route::view('/register', 'auth::register')->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.update');
});

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('throttle:auth')
    ->name('password.email');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/2fa/challenge', [StudentTwoFactorController::class, 'show'])
        ->name('student.2fa.challenge');
    Route::post('/2fa/challenge', [StudentTwoFactorController::class, 'verify'])
        ->middleware('throttle:auth')
        ->name('student.2fa.challenge.verify');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/settings', [ProfileController::class, 'redirectLegacySettings'])->name('settings.edit');
    Route::put('/settings/profile', [ProfileController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/avatar', [ProfileController::class, 'updateAvatar'])->name('settings.avatar');
    Route::delete('/settings/avatar', [ProfileController::class, 'destroyAvatar'])->name('settings.avatar.destroy');
    Route::put('/settings/objective', [ProfileController::class, 'updateObjective'])->name('settings.objective');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('settings.password');
    Route::put('/settings/notifications', [ProfileController::class, 'updateNotifications'])->name('settings.notifications');
    Route::post('/settings/redeem', [ProfileController::class, 'redeemCode'])->name('settings.redeem');
    Route::post('/settings/org-license', [ProfileController::class, 'activateOrgLicense'])->name('settings.org-license');
    Route::post('/settings/org-license/renew', [ProfileController::class, 'renewOrgLicense'])->name('settings.org-license.renew');
    Route::put('/settings/notes', [ProfileController::class, 'updateNotes'])->name('settings.notes');

    Route::get('/settings/2fa/setup', [SettingsTwoFactorController::class, 'showSetup'])
        ->name('settings.2fa.setup');
    Route::post('/settings/2fa/confirm', [SettingsTwoFactorController::class, 'confirmSetup'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.confirm');
    Route::get('/settings/2fa/recovery', [SettingsTwoFactorController::class, 'showRecovery'])
        ->name('settings.2fa.recovery');
    Route::post('/settings/2fa/recovery', [SettingsTwoFactorController::class, 'finishRecovery'])
        ->name('settings.2fa.recovery.finish');
    Route::delete('/settings/2fa', [SettingsTwoFactorController::class, 'disable'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.disable');
});
