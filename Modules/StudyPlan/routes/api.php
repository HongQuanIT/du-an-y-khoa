<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudyPlan\Http\Controllers\Api\StudyPlanApiController;
use Modules\StudyPlan\Http\Controllers\Api\StudyPlanTaskApiController;

/*
| StudyPlan — API v1 routes (prefix `api/v1`, names `api.study-plan.*`).
| learning path CRUD/progress. See srs/modules/04.
*/

Route::middleware('auth:sanctum')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('study-plans', [StudyPlanApiController::class, 'index'])->name('plans.index');
        Route::post('study-plans', [StudyPlanApiController::class, 'store'])->name('plans.store');
        Route::get('study-plans/{plan}', [StudyPlanApiController::class, 'show'])->name('plans.show');
        Route::put('study-plans/{plan}', [StudyPlanApiController::class, 'update'])->name('plans.update');
        Route::delete('study-plans/{plan}', [StudyPlanApiController::class, 'destroy'])->name('plans.destroy');

        Route::get('study-plans/{plan}/tasks', [StudyPlanTaskApiController::class, 'index'])->name('tasks.index');
        Route::post('study-plans/{plan}/tasks/{task}/start', [StudyPlanTaskApiController::class, 'start'])->name('tasks.start');
        Route::post('study-plans/{plan}/tasks/{task}/skip', [StudyPlanTaskApiController::class, 'skip'])->name('tasks.skip');
        Route::patch('study-plans/{plan}/tasks/{task}', [StudyPlanTaskApiController::class, 'reschedule'])->name('tasks.reschedule');
    });
