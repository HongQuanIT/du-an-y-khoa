<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Instructor portal (prefix `teach`, name `teach.`)
|--------------------------------------------------------------------------
| Separate from learner `/login` and admin `/admin/login` (same web guard).
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'classroom::teach.auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'storeTeach'])
        ->middleware('throttle:auth')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroyTeach'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'instructor'])->group(function (): void {
    Route::view('/', 'classroom::teach.dashboard')->name('dashboard');
});
