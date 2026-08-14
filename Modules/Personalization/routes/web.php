<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Personalization\Http\Controllers\FlashcardCreateController;
use Modules\Personalization\Http\Controllers\FlashcardDeckDetailController;
use Modules\Personalization\Http\Controllers\FlashcardReviewController;
use Modules\Personalization\Http\Controllers\FlashcardsDashboardController;
use Modules\Personalization\Http\Controllers\QuestionBookmarkController;

/*
| Personalization — web routes (notes, flashcards). Add pages here.
*/

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::post('/bookmarks/questions/{question}', QuestionBookmarkController::class)
        ->middleware('throttle:60,1')
        ->name('bookmarks.questions.set');

    Route::get('/flashcards', FlashcardsDashboardController::class)->name('flashcards.index');
    Route::get('/flashcards/create', FlashcardCreateController::class)->name('flashcards.create');
    Route::get('/flashcards/deck', FlashcardDeckDetailController::class)->name('flashcards.deck');
    Route::get('/flashcards/review', FlashcardReviewController::class)->name('flashcards.review');
});
