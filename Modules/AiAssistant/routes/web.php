<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Controllers\AiTutorController;
use Modules\AiAssistant\Http\Controllers\AiTutorPageController;

/*
| AI Tutor — session-authenticated routes (see srs/modules/08-ai-tutor-drawer.md).
|
| These are web routes (session + CSRF), matching the app's existing browser-AJAX
| convention (bookmarks, session annotate) rather than token-auth api/v1. Streaming
| replies are delivered over Reverb on the private `user.{id}` channel; when no
| realtime driver is configured the reply is generated synchronously as a fallback.
*/
Route::middleware(['auth'])->prefix('ai')->name('ai-tutor.')->group(function (): void {
    Route::get('/', AiTutorPageController::class)->name('page');

    Route::get('/quota', [AiTutorController::class, 'quota'])->name('quota');

    Route::post('/threads', [AiTutorController::class, 'storeThread'])->name('threads.store');
    Route::get('/threads/{thread}', [AiTutorController::class, 'showThread'])->name('threads.show');
    Route::post('/threads/{thread}/messages', [AiTutorController::class, 'storeMessage'])->name('threads.messages.store');
    Route::post('/threads/{thread}/stop', [AiTutorController::class, 'stop'])->name('threads.stop');

    Route::post('/messages/{message}/feedback', [AiTutorController::class, 'feedback'])->name('messages.feedback');
});
