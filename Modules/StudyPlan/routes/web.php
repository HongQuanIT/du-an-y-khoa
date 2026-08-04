<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudyPlan\Http\Controllers\StudyPlanCreateController;
use Modules\StudyPlan\Http\Controllers\StudyPlanDetailController;
use Modules\StudyPlan\Http\Controllers\StudyPlanPageController;
use Modules\StudyPlan\Http\Controllers\StudyPlanScheduleController;
use Modules\StudyPlan\Http\Controllers\StudyPlanSessionController;
use Modules\StudyPlan\Http\Controllers\StudyPlanTaskController;

/*
| StudyPlan — web routes. Tasks are scoped to their plan so a task id from
| another plan cannot be addressed (srs/modules/04 §13).
*/

Route::middleware('auth')
    ->prefix('study-plan')
    ->name('study-plan.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', StudyPlanPageController::class)->name('index');

        Route::get('/create', [StudyPlanCreateController::class, 'create'])->name('create');
        Route::post('/', [StudyPlanCreateController::class, 'store'])->name('store');

        Route::get('/{plan}', StudyPlanDetailController::class)->name('detail');
        Route::get('/{plan}/schedule', StudyPlanScheduleController::class)->name('schedule');

        Route::post('/{plan}/tasks/{task}/start', [StudyPlanTaskController::class, 'start'])->name('tasks.start');
        Route::post('/{plan}/tasks/{task}/skip', [StudyPlanTaskController::class, 'skip'])->name('tasks.skip');
        Route::post('/{plan}/tasks/{task}/reschedule', [StudyPlanTaskController::class, 'reschedule'])->name('tasks.reschedule');

        Route::get('/{plan}/tasks/{task}/session', [StudyPlanSessionController::class, 'show'])->name('session');
        Route::post('/{plan}/tasks/{task}/session', [StudyPlanSessionController::class, 'answer'])->name('session.answer');
        Route::post('/{plan}/tasks/{task}/session/annotations', [StudyPlanSessionController::class, 'annotate'])->name('session.annotate');
        Route::get('/{plan}/tasks/{task}/summary', [StudyPlanSessionController::class, 'summary'])->name('session.summary');
        Route::get('/{plan}/tasks/{task}/review', [StudyPlanSessionController::class, 'review'])->name('session.review');
    });
