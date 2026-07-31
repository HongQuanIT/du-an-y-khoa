<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Personalization\Http\Controllers\FlashcardCreateController;
use Modules\Personalization\Http\Controllers\FlashcardDeckDetailController;
use Modules\Personalization\Http\Controllers\FlashcardReviewController;
use Modules\Personalization\Http\Controllers\FlashcardsDashboardController;

/*
| Personalization — web routes (notes, flashcards). Add pages here.
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/flashcards', FlashcardsDashboardController::class)->name('flashcards.index');
    Route::get('/flashcards/create', FlashcardCreateController::class)->name('flashcards.create');
    Route::get('/flashcards/deck', FlashcardDeckDetailController::class)->name('flashcards.deck');
    Route::get('/flashcards/review', FlashcardReviewController::class)->name('flashcards.review');
});
