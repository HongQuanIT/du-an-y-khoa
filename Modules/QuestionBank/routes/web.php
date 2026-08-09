<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\QuestionBank\Http\Controllers\CustomSessionController;
use Modules\QuestionBank\Http\Controllers\QuestionBankPageController;
use Modules\QuestionBank\Http\Controllers\QuestionReviewController;
use Modules\QuestionBank\Http\Controllers\SessionHistoryController;
use Modules\QuestionBank\Http\Controllers\SessionSummaryController;
use Modules\QuestionBank\Http\Controllers\StudySessionController;

/*
| Web (Blade/Livewire) routes for the QuestionBank module.
| Add server-rendered pages here; API lives in routes/api.php.
*/

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::get('/qbank', QuestionBankPageController::class)->name('qbank.index');
    Route::get('/qbank/create', [CustomSessionController::class, 'create'])->name('qbank.create');
    Route::post('/qbank/create', [CustomSessionController::class, 'store'])->name('qbank.store');
    Route::post('/qbank/create/count', [CustomSessionController::class, 'count'])->name('qbank.count');

    Route::get('/qbank/session/{session}', [StudySessionController::class, 'show'])->name('qbank.session');
    Route::post('/qbank/session/{session}/answer', [StudySessionController::class, 'answer'])->name('qbank.session.answer');
    Route::post('/qbank/session/{session}/annotation', [StudySessionController::class, 'annotate'])->name('qbank.session.annotate');
    Route::post('/qbank/session/{session}/pause', [StudySessionController::class, 'pause'])->name('qbank.session.pause');
    Route::post('/qbank/session/{session}/resume', [StudySessionController::class, 'resume'])->name('qbank.session.resume');
    Route::post('/qbank/session/{session}/finish', [StudySessionController::class, 'finish'])->name('qbank.session.finish');
    Route::patch('/qbank/session/{session}/name', [SessionHistoryController::class, 'rename'])->name('qbank.session.rename');
    Route::post('/qbank/session/{session}/repeat', [SessionHistoryController::class, 'repeat'])->name('qbank.session.repeat');
    Route::delete('/qbank/session/{session}', [SessionHistoryController::class, 'destroy'])->name('qbank.session.destroy');
    Route::get('/qbank/session/{session}/summary', SessionSummaryController::class)->name('qbank.summary');
    Route::get('/qbank/session/{session}/review', QuestionReviewController::class)->name('qbank.review');
});
