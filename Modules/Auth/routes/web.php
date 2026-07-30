<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\RegisteredUserController;

/*
| Auth — web routes (login/register/password screens).
| Password reset handling will be wired later.
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth::login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth');

    Route::view('/register', 'auth::register')->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
