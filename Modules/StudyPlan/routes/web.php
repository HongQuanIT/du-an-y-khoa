<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudyPlan\Http\Controllers\StudyPlanCreateController;
use Modules\StudyPlan\Http\Controllers\StudyPlanDetailController;
use Modules\StudyPlan\Http\Controllers\StudyPlanPageController;
use Modules\StudyPlan\Http\Controllers\StudyPlanScheduleController;
use Modules\StudyPlan\Http\Controllers\StudyPlanSessionController;

/*
| StudyPlan — web routes. Add study-plan pages here.
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/study-plan', StudyPlanPageController::class)->name('study-plan.index');
    Route::get('/study-plan/create', StudyPlanCreateController::class)->name('study-plan.create');
    Route::get('/study-plan/detail', StudyPlanDetailController::class)->name('study-plan.detail');
    Route::get('/study-plan/schedule', StudyPlanScheduleController::class)->name('study-plan.schedule');
    Route::get('/study-plan/session', StudyPlanSessionController::class)->name('study-plan.session');
});
