<?php

declare(strict_types=1);

use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (prefix `api/v1` set in bootstrap/app.php)
|--------------------------------------------------------------------------
| Module-specific endpoints are auto-registered by each module's provider.
| Shared/cross-cutting endpoints live here.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', fn (Request $request) => ApiResponse::item(
        $request->user()->only(['id', 'name', 'email', 'locale']),
    ))->name('me');
});
