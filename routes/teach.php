<?php

declare(strict_types=1);

use App\Support\Enums\Permission;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
use Modules\Classroom\Http\Controllers\LiveMessageApiController;
use Modules\Classroom\Http\Controllers\LiveModerationController;
use Modules\Classroom\Http\Controllers\LivePresenterController;
use Modules\Classroom\Http\Controllers\LiveQuestionController;
use Modules\Classroom\Http\Controllers\LiveRoomApiController;
use Modules\Classroom\Http\Controllers\LiveTextMarksController;
use Modules\Classroom\Http\Controllers\TeachClassroomController;
use Modules\Classroom\Http\Controllers\TeachProfileController;
use Modules\Classroom\Http\Controllers\TeachQuestionReviewController;
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

Route::middleware('auth')->group(function (): void {
    Route::get('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'showTeach'])
        ->name('2fa.challenge');
    Route::post('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'verifyTeach'])
        ->name('2fa.challenge.verify');
});

Route::middleware(['auth', 'instructor', 'instructor.2fa'])->group(function (): void {
    Route::view('/', 'classroom::teach.dashboard')
        ->middleware('permission:teaching_dashboard.view|'.Permission::ClassroomManage->value.'|'.Permission::QuestionReview->value)
        ->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('permission:teach_notification.view')
        ->name('notifications.index');

    Route::get('/classes', [TeachClassroomController::class, 'index'])->middleware('permission:classroom.view')->name('classes.index');
    Route::get('/classes/create', [TeachClassroomController::class, 'create'])->middleware('permission:classroom.create|'.Permission::ClassroomCreate->value)->name('classes.create');
    Route::post('/classes', [TeachClassroomController::class, 'store'])->middleware('permission:classroom.create|'.Permission::ClassroomCreate->value)->name('classes.store');
    Route::get('/classes/{classroom}/edit', [TeachClassroomController::class, 'edit'])->middleware('permission:classroom_settings.update')->name('classes.edit');
    Route::put('/classes/{classroom}', [TeachClassroomController::class, 'update'])->middleware('permission:classroom_settings.update')->name('classes.update');
    Route::get('/classes/{classroom}', [TeachClassroomController::class, 'show'])->middleware('permission:classroom.view|'.Permission::ClassroomManage->value)->name('classes.show');
    Route::post('/classes/{classroom}/close', [TeachClassroomController::class, 'close'])->middleware('permission:classroom.close')->name('classes.close');
    Route::post('/classes/{classroom}/reopen', [TeachClassroomController::class, 'reopen'])->middleware('permission:classroom.reopen')->name('classes.reopen');
    Route::delete('/classes/{classroom}', [TeachClassroomController::class, 'destroy'])->middleware('permission:classroom.delete')->name('classes.destroy');
    Route::get('/classes/{classroom}/questions/search', [TeachClassroomController::class, 'searchQuestions'])
        ->middleware('permission:classroom_settings.update')
        ->name('classes.questions.search');
    Route::get('/classes/{classroom}/questions/{question}/feedback', [TeachClassroomController::class, 'questionFeedback'])
        ->middleware('permission:classroom.view|'.Permission::ClassroomManage->value)
        ->name('classes.questions.feedback');
    Route::post('/classes/{classroom}/members/{user}/kick', [LiveModerationController::class, 'kickMember'])
        ->middleware('permission:classroom_settings.update')
        ->name('classes.members.kick');
    Route::post('/classes/{classroom}/sessions', [TeachClassroomController::class, 'scheduleLive'])->middleware('permission:classroom_session.schedule')->name('classes.sessions.store');
    Route::post('/classes/{classroom}/sessions/{liveSession}/start', [TeachClassroomController::class, 'startLive'])
        ->middleware('permission:classroom_session.start')
        ->scopeBindings()->name('classes.sessions.start');
    Route::post('/classes/{classroom}/sessions/{liveSession}/end', [TeachClassroomController::class, 'endLive'])
        ->middleware('permission:classroom_session.end')
        ->scopeBindings()->name('classes.sessions.end');
    Route::get('/classes/{classroom}/sessions/{liveSession}/studio', [TeachClassroomController::class, 'studio'])
        ->middleware('permission:classroom_session.start')
        ->scopeBindings()->name('classes.sessions.studio');
    Route::get('/classes/{classroom}/sessions/{liveSession}/studio/presenter', [LivePresenterController::class, 'show'])
        ->middleware('permission:classroom_session.start')
        ->scopeBindings()->name('classes.sessions.studio.presenter');
    Route::prefix('/classes/{classroom}/sessions/{liveSession}/studio/api')
        ->name('classes.sessions.studio.api.')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/bootstrap', [LiveRoomApiController::class, 'bootstrap'])
                ->middleware(['permission:classroom.view', 'permission:classroom_session.start'])
                ->name('bootstrap');
            Route::post('/token', [LiveRoomApiController::class, 'refreshToken'])->middleware('permission:classroom_session.start')->name('token');
            Route::post('/messages', [LiveMessageApiController::class, 'store'])->middleware(['permission:classroom.view', 'throttle:30,1'])->name('messages');
            Route::post('/messages/{message}/pin', [LiveMessageApiController::class, 'pin'])->middleware('permission:classroom_session.start')->name('messages.pin');
            Route::delete('/messages/{message}', [LiveMessageApiController::class, 'destroy'])->middleware('permission:classroom_session.start')->name('messages.destroy');
            Route::get('/question', [LiveQuestionController::class, 'show'])->middleware('permission:classroom_session.start')->name('question.show');
            Route::patch('/question', [LiveQuestionController::class, 'update'])->middleware('permission:classroom_session.start')->name('question');
            Route::patch('/marks', [LiveTextMarksController::class, 'update'])->middleware(['permission:classroom_session.start', 'throttle:60,1'])->name('marks');
            Route::post('/raise-hand', [LiveModerationController::class, 'raiseHand'])->middleware('permission:classroom.view')->name('raise-hand');
            Route::post('/hands/{hand}/dismiss', [LiveModerationController::class, 'dismissHand'])->middleware('permission:classroom_session.start')->name('hands.dismiss');
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
            Route::post('/react', [LiveModerationController::class, 'react'])->middleware(['permission:classroom.view', 'throttle:30,1'])->name('react');
            Route::post('/mute-chat', [LiveModerationController::class, 'muteChat'])->middleware('permission:classroom_session.start')->name('mute-chat');
            Route::post('/focus-questions', [LivePresenterController::class, 'focusQuestions'])->middleware('permission:classroom_session.start')->name('focus-questions');
            Route::patch('/stage', [LivePresenterController::class, 'updateStage'])->middleware('permission:classroom_session.start')->name('stage');
        });

    Route::get('/profile', [TeachProfileController::class, 'show'])->middleware('permission:teach_profile.view')->name('profile.show');
    Route::put('/profile', [TeachProfileController::class, 'updateProfile'])->middleware('permission:teach_profile.update')->name('profile.update');
    Route::put('/profile/contact', [TeachProfileController::class, 'updateContact'])->middleware('permission:teach_profile.update')->name('profile.contact');
    Route::put('/profile/password', [TeachProfileController::class, 'updatePassword'])->middleware('permission:teach_profile.password_update')->name('profile.password');
    Route::put('/profile/avatar', [TeachProfileController::class, 'updateAvatar'])->middleware('permission:teach_profile.avatar_update')->name('profile.avatar');
    Route::delete('/profile/avatar', [TeachProfileController::class, 'destroyAvatar'])->middleware('permission:teach_profile.avatar_update')->name('profile.avatar.destroy');

    Route::get('/questions/reviews', [TeachQuestionReviewController::class, 'index'])
        ->middleware('permission:question.review')
        ->name('questions.reviews.index');
    Route::get('/questions/reviews/{question}', [TeachQuestionReviewController::class, 'show'])
        ->middleware('permission:question.review')
        ->name('questions.reviews.show');
    Route::post('/questions/reviews/{question}/approve', [TeachQuestionReviewController::class, 'approve'])
        ->middleware('permission:question.review')
        ->name('questions.reviews.approve');
    Route::post('/questions/reviews/{question}/reject', [TeachQuestionReviewController::class, 'reject'])
        ->middleware('permission:question.review')
        ->name('questions.reviews.reject');
});
