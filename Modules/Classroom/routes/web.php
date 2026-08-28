<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Classroom\Http\Controllers\ClassroomIndexController;
use Modules\Classroom\Http\Controllers\ClassroomInviteController;
use Modules\Classroom\Http\Controllers\ClassroomMembershipController;
use Modules\Classroom\Http\Controllers\ClassroomSettingsController;
use Modules\Classroom\Http\Controllers\ClassroomShowController;
use Modules\Classroom\Http\Controllers\LiveKitWebhookController;
use Modules\Classroom\Http\Controllers\LiveMessageApiController;
use Modules\Classroom\Http\Controllers\LiveModerationController;
use Modules\Classroom\Http\Controllers\LivePresenterController;
use Modules\Classroom\Http\Controllers\LiveQuestionController;
use Modules\Classroom\Http\Controllers\LiveRoomApiController;
use Modules\Classroom\Http\Controllers\LiveRoomController;
use Modules\Classroom\Http\Controllers\LiveSessionController;

Route::post('/webhooks/livekit', LiveKitWebhookController::class)->name('webhooks.livekit');

Route::middleware(['auth', 'learner'])
    ->prefix('classes')
    ->name('classroom.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', ClassroomIndexController::class)->name('index');

        Route::get('/{classroom}', ClassroomShowController::class)->name('show');
        Route::get('/{classroom}/settings', [ClassroomSettingsController::class, 'edit'])->name('settings');
        Route::patch('/{classroom}/settings', [ClassroomSettingsController::class, 'update'])->name('settings.update');
        Route::post('/{classroom}/invite', [ClassroomInviteController::class, 'store'])->name('invite');
        Route::post('/{classroom}/join', [ClassroomMembershipController::class, 'join'])->name('join');
        Route::post('/{classroom}/leave', [ClassroomMembershipController::class, 'leave'])->name('leave');

        Route::post('/{classroom}/sessions', [LiveSessionController::class, 'store'])->name('sessions.store');
        Route::post('/{classroom}/sessions/{liveSession}/start', [LiveSessionController::class, 'start'])->name('sessions.start');
        Route::post('/{classroom}/sessions/{liveSession}/end', [LiveSessionController::class, 'end'])->name('sessions.end');

        Route::get('/{classroom}/live/{liveSession}/presenter', [LivePresenterController::class, 'show'])
            ->name('live.presenter');

        Route::get('/{classroom}/live/{liveSession}', [LiveRoomController::class, 'show'])->name('live');
        Route::post('/{classroom}/live/{liveSession}/messages', [LiveRoomController::class, 'message'])->name('live.message');

        Route::prefix('/{classroom}/live/{liveSession}/api')->name('live.api.')->group(function (): void {
            Route::get('/bootstrap', [LiveRoomApiController::class, 'bootstrap'])->name('bootstrap');
            Route::post('/token', [LiveRoomApiController::class, 'refreshToken'])->name('token');
            Route::post('/messages', [LiveMessageApiController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('messages');
            Route::post('/messages/{message}/pin', [LiveMessageApiController::class, 'pin'])->name('messages.pin');
            Route::delete('/messages/{message}', [LiveMessageApiController::class, 'destroy'])->name('messages.destroy');
            Route::patch('/question', [LiveQuestionController::class, 'update'])->name('question');
            Route::post('/raise-hand', [LiveModerationController::class, 'raiseHand'])->name('raise-hand');
            Route::post('/hands/{hand}/dismiss', [LiveModerationController::class, 'dismissHand'])->name('hands.dismiss');
            Route::post('/react', [LiveModerationController::class, 'react'])
                ->middleware('throttle:30,1')
                ->name('react');
            Route::post('/mute-chat', [LiveModerationController::class, 'muteChat'])->name('mute-chat');
            Route::post('/focus-questions', [LivePresenterController::class, 'focusQuestions'])->name('focus-questions');
        });

        Route::post('/{classroom}/members/{user}/ban', [LiveModerationController::class, 'banMember'])
            ->name('members.ban');

    });
