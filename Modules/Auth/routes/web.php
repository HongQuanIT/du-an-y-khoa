<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PasswordResetController;
use Modules\Auth\Http\Controllers\RegisteredUserController;

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

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
