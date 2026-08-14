<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Classroom\Http\Controllers\TeachClassroomController;
use Modules\Classroom\Http\Controllers\TeachProfileController;

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

    Route::get('/classes', [TeachClassroomController::class, 'index'])->name('classes.index');
    Route::get('/classes/create', [TeachClassroomController::class, 'create'])->name('classes.create');
    Route::post('/classes', [TeachClassroomController::class, 'store'])->name('classes.store');
    Route::get('/classes/{classroom}', [TeachClassroomController::class, 'show'])->name('classes.show');

    Route::get('/profile', [TeachProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [TeachProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/contact', [TeachProfileController::class, 'updateContact'])->name('profile.contact');
    Route::put('/profile/password', [TeachProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/avatar', [TeachProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [TeachProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
});
