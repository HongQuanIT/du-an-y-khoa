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
use Modules\Classroom\Http\Controllers\LiveTextMarksController;

Route::post('/webhooks/livekit', LiveKitWebhookController::class)->name('webhooks.livekit');

Route::middleware(['auth', 'learner', 'permission:classroom.view'])
    ->prefix('classes')
    ->name('classroom.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', ClassroomIndexController::class)->name('index');

        Route::get('/{classroom}', ClassroomShowController::class)->name('show');
        Route::get('/{classroom}/settings', [ClassroomSettingsController::class, 'edit'])->middleware('permission:classroom_settings.update')->name('settings');
        Route::patch('/{classroom}/settings', [ClassroomSettingsController::class, 'update'])->middleware('permission:classroom_settings.update')->name('settings.update');
        Route::post('/{classroom}/invite', [ClassroomInviteController::class, 'store'])->middleware('permission:classroom_member.invite')->name('invite');
        Route::post('/{classroom}/join', [ClassroomMembershipController::class, 'join'])->middleware('permission:classroom.join')->name('join');
        Route::post('/{classroom}/leave', [ClassroomMembershipController::class, 'leave'])->middleware('permission:classroom.leave')->name('leave');

        Route::post('/{classroom}/sessions', [LiveSessionController::class, 'store'])->middleware('permission:classroom_session.schedule')->name('sessions.store');
        Route::post('/{classroom}/sessions/{liveSession}/start', [LiveSessionController::class, 'start'])->middleware('permission:classroom_session.start')->name('sessions.start');
        Route::post('/{classroom}/sessions/{liveSession}/end', [LiveSessionController::class, 'end'])->middleware('permission:classroom_session.end')->name('sessions.end');

        Route::get('/{classroom}/live/{liveSession}/presenter', [LivePresenterController::class, 'show'])
            ->middleware('permission:classroom.view')
            ->name('live.presenter');

        Route::get('/{classroom}/live/{liveSession}', [LiveRoomController::class, 'show'])->middleware('permission:classroom.view')->name('live');
        Route::post('/{classroom}/live/{liveSession}/messages', [LiveRoomController::class, 'message'])->middleware('permission:live_message.create')->name('live.message');

        Route::prefix('/{classroom}/live/{liveSession}/api')->name('live.api.')->group(function (): void {
            Route::get('/bootstrap', [LiveRoomApiController::class, 'bootstrap'])->middleware('permission:classroom.view')->name('bootstrap');
            Route::post('/token', [LiveRoomApiController::class, 'refreshToken'])->middleware('permission:classroom.view')->name('token');
            Route::post('/messages', [LiveMessageApiController::class, 'store'])
                ->middleware(['permission:live_message.create', 'throttle:30,1'])
                ->name('messages');
            Route::post('/messages/{message}/pin', [LiveMessageApiController::class, 'pin'])->middleware('permission:live_message.manage')->name('messages.pin');
            Route::delete('/messages/{message}', [LiveMessageApiController::class, 'destroy'])->middleware('permission:live_message.manage')->name('messages.destroy');
            Route::get('/question', [LiveQuestionController::class, 'show'])->middleware('permission:live_question.view')->name('question.show');
            Route::patch('/question', [LiveQuestionController::class, 'update'])->middleware('permission:live_question.update')->name('question');
            Route::patch('/marks', [LiveTextMarksController::class, 'update'])
                ->middleware(['permission:classroom_session.start', 'throttle:60,1'])
                ->name('marks');
            Route::post('/raise-hand', [LiveModerationController::class, 'raiseHand'])->middleware('permission:live_hand.raise')->name('raise-hand');
            Route::post('/hands/{hand}/dismiss', [LiveModerationController::class, 'dismissHand'])->middleware('permission:live_hand.manage')->name('hands.dismiss');
            Route::post('/speakers/{user}/invite', [LiveModerationController::class, 'inviteSpeaker'])
                ->middleware('permission:classroom_session.start')
                ->withoutScopedBindings()
                ->name('speakers.invite');
            Route::post('/speakers/{user}/mute', [LiveModerationController::class, 'muteSpeaker'])
                ->middleware('permission:classroom_session.start')
                ->withoutScopedBindings()
                ->name('speakers.mute');
            Route::post('/speakers/{user}/unmute', [LiveModerationController::class, 'unmuteSpeaker'])
                ->middleware('permission:classroom_session.start')
                ->withoutScopedBindings()
                ->name('speakers.unmute');
            Route::post('/react', [LiveModerationController::class, 'react'])
                ->middleware(['permission:classroom.view', 'throttle:30,1'])
                ->name('react');
            Route::post('/mute-chat', [LiveModerationController::class, 'muteChat'])->middleware('permission:live_chat.mute')->name('mute-chat');
            Route::post('/focus-questions', [LivePresenterController::class, 'focusQuestions'])->middleware('permission:classroom_session.start')->name('focus-questions');
            Route::patch('/stage', [LivePresenterController::class, 'updateStage'])->middleware('permission:classroom_session.start')->name('stage');
        });

        Route::post('/{classroom}/members/{user}/ban', [LiveModerationController::class, 'banMember'])
            ->middleware('permission:classroom_member.ban')
            ->name('members.ban');

    });
