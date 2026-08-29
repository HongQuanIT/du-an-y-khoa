<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Classroom\Http\Controllers\LiveMessageApiController;
use Modules\Classroom\Http\Controllers\LiveModerationController;
use Modules\Classroom\Http\Controllers\LivePresenterController;
use Modules\Classroom\Http\Controllers\LiveQuestionController;
use Modules\Classroom\Http\Controllers\LiveRoomApiController;
use Modules\Classroom\Http\Controllers\LiveTextMarksController;
use Modules\Classroom\Http\Controllers\TeachClassroomController;
use Modules\Classroom\Http\Controllers\TeachProfileController;
use Modules\Notification\Http\Controllers\NotificationController;

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

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::get('/classes', [TeachClassroomController::class, 'index'])->name('classes.index');
    Route::get('/classes/create', [TeachClassroomController::class, 'create'])->name('classes.create');
    Route::post('/classes', [TeachClassroomController::class, 'store'])->name('classes.store');
    Route::get('/classes/{classroom}/edit', [TeachClassroomController::class, 'edit'])->name('classes.edit');
    Route::put('/classes/{classroom}', [TeachClassroomController::class, 'update'])->name('classes.update');
    Route::get('/classes/{classroom}', [TeachClassroomController::class, 'show'])->name('classes.show');
    Route::post('/classes/{classroom}/close', [TeachClassroomController::class, 'close'])->name('classes.close');
    Route::post('/classes/{classroom}/reopen', [TeachClassroomController::class, 'reopen'])->name('classes.reopen');
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
            Route::patch('/marks', [LiveTextMarksController::class, 'update'])->middleware('throttle:60,1')->name('marks');
            Route::post('/raise-hand', [LiveModerationController::class, 'raiseHand'])->name('raise-hand');
            Route::post('/hands/{hand}/dismiss', [LiveModerationController::class, 'dismissHand'])->name('hands.dismiss');
            Route::post('/speakers/{user}/invite', [LiveModerationController::class, 'inviteSpeaker'])
                ->withoutScopedBindings()
                ->name('speakers.invite');
            Route::post('/speakers/{user}/mute', [LiveModerationController::class, 'muteSpeaker'])
                ->withoutScopedBindings()
                ->name('speakers.mute');
            Route::post('/speakers/{user}/unmute', [LiveModerationController::class, 'unmuteSpeaker'])
                ->withoutScopedBindings()
                ->name('speakers.unmute');
            Route::post('/react', [LiveModerationController::class, 'react'])->middleware('throttle:30,1')->name('react');
            Route::post('/mute-chat', [LiveModerationController::class, 'muteChat'])->name('mute-chat');
            Route::post('/focus-questions', [LivePresenterController::class, 'focusQuestions'])->name('focus-questions');
            Route::patch('/stage', [LivePresenterController::class, 'updateStage'])->name('stage');
        });

    Route::get('/profile', [TeachProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [TeachProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/contact', [TeachProfileController::class, 'updateContact'])->name('profile.contact');
    Route::put('/profile/password', [TeachProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/avatar', [TeachProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [TeachProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
});
