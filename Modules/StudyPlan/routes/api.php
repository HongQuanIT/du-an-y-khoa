<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudyPlan\Http\Controllers\Api\StudyPlanApiController;
use Modules\StudyPlan\Http\Controllers\Api\StudyPlanTaskApiController;

/*
| StudyPlan — API v1 routes (prefix `api/v1`, names `api.study-plan.*`).
| learning path CRUD/progress. See srs/modules/04.
*/

Route::middleware(['auth:sanctum', 'portal:learner'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('study-plans', [StudyPlanApiController::class, 'index'])->middleware('permission:study_plan.view_any')->name('plans.index');
        Route::post('study-plans', [StudyPlanApiController::class, 'store'])->middleware('permission:study_plan.create')->name('plans.store');
        Route::get('study-plans/{plan}', [StudyPlanApiController::class, 'show'])->middleware('permission:study_plan.view')->name('plans.show');

        Route::get('study-plans/{plan}/tasks', [StudyPlanTaskApiController::class, 'index'])->middleware('permission:study_plan.view')->name('tasks.index');
        Route::post('study-plans/{plan}/tasks/{task}/start', [StudyPlanTaskApiController::class, 'start'])->middleware('permission:study_plan_task.start')->name('tasks.start');
        Route::post('study-plans/{plan}/tasks/{task}/skip', [StudyPlanTaskApiController::class, 'skip'])->middleware('permission:study_plan_task.skip')->name('tasks.skip');
    });
