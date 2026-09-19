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

Route::middleware(['auth', 'learner', 'permission:study_plan.view'])
    ->prefix('study-plan')
    ->name('study-plan.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', StudyPlanPageController::class)
            ->middleware('permission:study_plan.view|study_plan.create|study_plan_task.start|study_plan_task.complete|study_plan_task.review')
            ->name('index');

        Route::get('/create', [StudyPlanCreateController::class, 'create'])->middleware('permission:study_plan.create')->name('create');
        Route::post('/', [StudyPlanCreateController::class, 'store'])->middleware('permission:study_plan.create')->name('store');

        Route::get('/{plan}', StudyPlanDetailController::class)->middleware('permission:study_plan.view')->name('detail');
        Route::get('/{plan}/schedule', StudyPlanScheduleController::class)->middleware('permission:study_plan.view')->name('schedule');

        Route::post('/{plan}/tasks/{task}/start', [StudyPlanTaskController::class, 'start'])->middleware('permission:study_plan_task.start')->name('tasks.start');
        Route::post('/{plan}/tasks/{task}/skip', [StudyPlanTaskController::class, 'skip'])->middleware('permission:study_plan_task.start')->name('tasks.skip');

        Route::get('/{plan}/tasks/{task}/session', [StudyPlanSessionController::class, 'show'])->middleware('permission:study_plan_task.start')->name('session');
        Route::post('/{plan}/tasks/{task}/session', [StudyPlanSessionController::class, 'answer'])->middleware('permission:session.start')->name('session.answer');
        Route::post('/{plan}/tasks/{task}/session/annotations', [StudyPlanSessionController::class, 'annotate'])->middleware('permission:session.submit')->name('session.annotate');
        Route::get('/{plan}/tasks/{task}/summary', [StudyPlanSessionController::class, 'summary'])->middleware('permission:session.review')->name('session.summary');
        Route::get('/{plan}/tasks/{task}/review', [StudyPlanSessionController::class, 'review'])->middleware('permission:study_plan_task.review')->name('session.review');
    });
