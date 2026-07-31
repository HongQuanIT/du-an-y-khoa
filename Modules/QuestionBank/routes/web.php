<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\QuestionBank\Http\Controllers\CustomSessionController;
use Modules\QuestionBank\Http\Controllers\ExamSessionController;
use Modules\QuestionBank\Http\Controllers\QuestionBankPageController;
use Modules\QuestionBank\Http\Controllers\QuestionReviewController;
use Modules\QuestionBank\Http\Controllers\SessionSummaryController;
use Modules\QuestionBank\Http\Controllers\StudySessionController;

/*
| Web (Blade/Livewire) routes for the QuestionBank module.
| Add server-rendered pages here; API lives in routes/api.php.
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/qbank', QuestionBankPageController::class)->name('qbank.index');
    Route::get('/qbank/create', CustomSessionController::class)->name('qbank.create');
    Route::get('/qbank/session', StudySessionController::class)->name('qbank.session');
    Route::get('/qbank/exam', ExamSessionController::class)->name('qbank.exam');
    Route::get('/qbank/summary', SessionSummaryController::class)->name('qbank.summary');
    Route::get('/qbank/review', QuestionReviewController::class)->name('qbank.review');
});
