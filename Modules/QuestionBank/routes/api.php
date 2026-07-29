<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\QuestionBank\Http\Controllers\QuestionController;

/*
| Registered by ModuleRouteServiceProvider under the `api/v1` prefix with
| route-name prefix `api.question-bank.`.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('questions/{question}', [QuestionController::class, 'show'])->name('questions.show');
});
