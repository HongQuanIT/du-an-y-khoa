<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Classroom\Http\Controllers\LiveMessageApiController;
use Modules\Classroom\Http\Controllers\LiveModerationController;
use Modules\Classroom\Http\Controllers\LivePresenterController;
use Modules\Classroom\Http\Controllers\LiveQuestionController;
use Modules\Classroom\Http\Controllers\LiveRoomApiController;
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
    Route::delete('/classes/{classroom}', [TeachClassroomController::class, 'destroy'])->name('classes.destroy');
    Route::get('/classes/{classroom}/questions/search', [TeachClassroomController::class, 'searchQuestions'])
        ->name('classes.questions.search');
    Route::post('/classes/{classroom}/members/{user}/kick', [LiveModerationController::class, 'kickMember'])
        ->name('classes.members.kick');
    Route::post('/classes/{classroom}/sessions', [TeachClassroomController::class, 'scheduleLive'])->name('classes.sessions.store');
    Route::post('/classes/{classroom}/sessions/{liveSession}/start', [TeachClassroomController::class, 'startLive'])
        ->scopeBindings()->name('classes.sessions.start');
    Route::post('/classes/{classroom}/sessions/{liveSession}/end', [TeachClassroomController::class, 'endLive'])
        ->scopeBindings()->name('classes.sessions.end');
    Route::get('/classes/{classroom}/sessions/{liveSession}/studio', [TeachClassroomController::class, 'studio'])
        ->scopeBindings()->name('classes.sessions.studio');
    Route::get('/classes/{classroom}/sessions/{liveSession}/studio/presenter', [LivePresenterController::class, 'show'])
        ->scopeBindings()->name('classes.sessions.studio.presenter');
    Route::prefix('/classes/{classroom}/sessions/{liveSession}/studio/api')
        ->name('classes.sessions.studio.api.')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/bootstrap', [LiveRoomApiController::class, 'bootstrap'])->name('bootstrap');
            Route::post('/token', [LiveRoomApiController::class, 'refreshToken'])->name('token');
            Route::post('/messages', [LiveMessageApiController::class, 'store'])->middleware('throttle:30,1')->name('messages');
            Route::post('/messages/{message}/pin', [LiveMessageApiController::class, 'pin'])->name('messages.pin');
            Route::delete('/messages/{message}', [LiveMessageApiController::class, 'destroy'])->name('messages.destroy');
            Route::patch('/question', [LiveQuestionController::class, 'update'])->name('question');
            Route::post('/raise-hand', [LiveModerationController::class, 'raiseHand'])->name('raise-hand');
            Route::post('/hands/{hand}/dismiss', [LiveModerationController::class, 'dismissHand'])->name('hands.dismiss');
            Route::post('/react', [LiveModerationController::class, 'react'])->middleware('throttle:30,1')->name('react');
            Route::post('/mute-chat', [LiveModerationController::class, 'muteChat'])->name('mute-chat');
            Route::post('/focus-questions', [LivePresenterController::class, 'focusQuestions'])->name('focus-questions');
        });

    Route::get('/profile', [TeachProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [TeachProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/contact', [TeachProfileController::class, 'updateContact'])->name('profile.contact');
    Route::put('/profile/password', [TeachProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/avatar', [TeachProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [TeachProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
});
