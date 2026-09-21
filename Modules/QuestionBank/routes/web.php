<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\QuestionBank\Http\Controllers\CustomSessionController;
use Modules\QuestionBank\Http\Controllers\QuestionBankPageController;
use Modules\QuestionBank\Http\Controllers\QuestionBookmarkPageController;
use Modules\QuestionBank\Http\Controllers\QuestionFeedbackController;
use Modules\QuestionBank\Http\Controllers\QuestionReviewController;
use Modules\QuestionBank\Http\Controllers\SessionHistoryController;
use Modules\QuestionBank\Http\Controllers\SessionSummaryController;
use Modules\QuestionBank\Http\Controllers\StudySessionController;
use Modules\QuestionBank\Http\Controllers\TaxonomyLookupController;
use Modules\QuestionBank\Http\Controllers\WeakTopicSessionController;

/*
| Web (Blade/Livewire) routes for the QuestionBank module.
| Add server-rendered pages here; API lives in routes/api.php.
*/

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::get('/qbank', QuestionBankPageController::class)
        ->middleware('permission:question.view')
        ->name('qbank.index');
    Route::get('/qbank/bookmarks', [QuestionBookmarkPageController::class, 'index'])
        ->middleware('permission:bookmark.view')->name('qbank.bookmarks');
    Route::delete('/qbank/bookmarks/{question}', [QuestionBookmarkPageController::class, 'destroy'])
        ->middleware('permission:bookmark.delete')
        ->name('qbank.bookmarks.destroy');
    Route::post('/qbank/bookmarks/session', [QuestionBookmarkPageController::class, 'startSession'])
        ->middleware('permission:session.create')
        ->name('qbank.bookmarks.session');
    Route::get('/qbank/create', [CustomSessionController::class, 'create'])->middleware('permission:session.create')->name('qbank.create');
    Route::post('/qbank/create', [CustomSessionController::class, 'store'])->middleware('permission:session.create')->name('qbank.store');
    Route::post('/qbank/create/count', [CustomSessionController::class, 'count'])->middleware('permission:session.create')->name('qbank.count');
    Route::post('/qbank/weak-topics/{lesson}/session', WeakTopicSessionController::class)
        ->middleware('permission:session.create')
        ->name('qbank.weak-topics.session');

    Route::prefix('qbank/taxonomy/lookups')->name('qbank.taxonomy.lookups.')->middleware('permission:session.create')->group(function (): void {
        Route::get('/blueprints', [TaxonomyLookupController::class, 'blueprints'])->name('blueprints');
        Route::get('/blueprints/{blueprint}/sections', [TaxonomyLookupController::class, 'blueprintSections'])->name('sections');
        Route::get('/sections/{section}/core-topics', [TaxonomyLookupController::class, 'coreClinicalTopics'])->name('core-topics');
        Route::get('/core-topics/search', [TaxonomyLookupController::class, 'searchCoreClinicalTopics'])->name('core-topics.search');
        Route::get('/organ-systems', [TaxonomyLookupController::class, 'organSystems'])->name('organ-systems');
        Route::get('/subjects', [TaxonomyLookupController::class, 'subjects'])->name('subjects');
        Route::get('/lessons', [TaxonomyLookupController::class, 'lessons'])->name('lessons');
        Route::get('/tags', [TaxonomyLookupController::class, 'tags'])->name('tags');
    });

    Route::get('/qbank/session/{session}', [StudySessionController::class, 'show'])->middleware('permission:session.start')->name('qbank.session');
    Route::post('/qbank/session/{session}/answer', [StudySessionController::class, 'answer'])->middleware('permission:session.start')->name('qbank.session.answer');
    Route::post('/qbank/session/{session}/annotation', [StudySessionController::class, 'annotate'])->middleware('permission:session.submit')->name('qbank.session.annotate');
    Route::post('/qbank/session/{session}/feedback', [QuestionFeedbackController::class, 'store'])->middleware('permission:session.submit')->name('qbank.session.feedback');
    Route::post('/qbank/session/{session}/pause', [StudySessionController::class, 'pause'])->middleware('permission:session.submit')->name('qbank.session.pause');
    Route::post('/qbank/session/{session}/resume', [StudySessionController::class, 'resume'])->middleware('permission:session.submit')->name('qbank.session.resume');
    Route::post('/qbank/session/{session}/finish', [StudySessionController::class, 'finish'])->middleware('permission:session.submit')->name('qbank.session.finish');
    Route::patch('/qbank/session/{session}/name', [SessionHistoryController::class, 'rename'])->middleware('permission:session.submit')->name('qbank.session.rename');
    Route::post('/qbank/session/{session}/repeat', [SessionHistoryController::class, 'repeat'])->middleware('permission:session.repeat')->name('qbank.session.repeat');
    Route::delete('/qbank/session/{session}', [SessionHistoryController::class, 'destroy'])->middleware('permission:session.delete')->name('qbank.session.destroy');
    Route::get('/qbank/session/{session}/summary', SessionSummaryController::class)->middleware('permission:session.review')->name('qbank.summary');
    Route::get('/qbank/session/{session}/review', QuestionReviewController::class)->middleware('permission:session.review')->name('qbank.review');
});
