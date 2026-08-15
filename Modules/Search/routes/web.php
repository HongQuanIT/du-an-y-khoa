<?php

declare(strict_types=1);

/*
| Search — web routes (search results page). Add pages here.
*/

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\GlobalSearchController;
use Modules\Search\Http\Controllers\GlobalSearchSuggestController;

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::get('/search', GlobalSearchController::class)->name('search.index');
    Route::get('/search/suggest', GlobalSearchSuggestController::class)->name('search.suggest');
});
