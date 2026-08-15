<?php

declare(strict_types=1);

/*
| Search — API v1 routes (prefix `api/v1`, names `api.search.*`).
| Contextual search remains on `/qbank?q=...`; this module also exposes
| global typeahead suggestions for the app-wide search box.
*/

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\GlobalSearchSuggestController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('search/suggest', GlobalSearchSuggestController::class)->name('search.suggest');
});
