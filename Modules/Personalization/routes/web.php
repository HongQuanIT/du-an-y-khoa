<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Personalization\Http\Controllers\BookmarkFolderController;
use Modules\Personalization\Http\Controllers\QuestionBookmarkController;

/*
| Personalization — web routes (notes, flashcards). Add pages here.
*/

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::post('/bookmarks/questions/{question}', QuestionBookmarkController::class)
        ->middleware(['permission:bookmark.create', 'throttle:60,1'])
        ->name('bookmarks.questions.set');

    Route::get('/bookmarks/folders', [BookmarkFolderController::class, 'index'])
        ->middleware('permission:bookmark.view')
        ->name('bookmarks.folders.index');
    Route::post('/bookmarks/folders', [BookmarkFolderController::class, 'store'])
        ->middleware('permission:bookmark.create')
        ->name('bookmarks.folders.store');
    Route::post('/bookmarks/folders/{folder}/toggle', [BookmarkFolderController::class, 'toggle'])
        ->middleware('permission:bookmark.update|bookmark.create')
        ->name('bookmarks.folders.toggle');
    Route::delete('/bookmarks/folders/{folder}', [BookmarkFolderController::class, 'destroy'])
        ->middleware('permission:bookmark.delete')
        ->name('bookmarks.folders.destroy');
});
