<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Exam\Http\Controllers\ExamIndexController;
use Modules\Exam\Http\Controllers\ExamSessionReviewController;
use Modules\Exam\Http\Controllers\ExamSessionSummaryController;
use Modules\Exam\Http\Controllers\StartExamController;
use Modules\QuestionBank\Http\Controllers\StudySessionController;

/*
| Exam — web routes (exam player, results). Add pages here.
*/

Route::middleware(['auth', 'learner'])
    ->prefix('exams')
    ->name('exam.')
    ->group(function (): void {
        Route::get('/', ExamIndexController::class)->middleware('permission:exam.view')->name('index');
        Route::post('/{exam}/start', StartExamController::class)
            ->middleware('subscription:exam.simulation')
            ->name('start');
        Route::get('/{session}/summary', ExamSessionSummaryController::class)->middleware('permission:exam.overview')->name('summary');
        Route::get('/{session}/review', ExamSessionReviewController::class)->middleware('permission:exam.review')->name('review');
        Route::get('/{session}', [StudySessionController::class, 'show'])->middleware('permission:exam.take')->name('session');
    });
