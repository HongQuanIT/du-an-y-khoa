<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
use Modules\Editor\Http\Controllers\EditorDashboardController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\TaxonomyController;
use Modules\Admin\Http\Controllers\Cms\PageController;
use Modules\Media\Http\Controllers\MediaController;
use Modules\QuestionBank\Http\Controllers\TaxonomyLookupController;

/*
|--------------------------------------------------------------------------
| Editor portal (prefix `editor`, name `editor.`)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'editor::auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'storeEditor'])
        ->middleware('throttle:auth')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroyEditor'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'portal:editor'])->group(function (): void {
    Route::get('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'showEditor'])->name('2fa.challenge');
    Route::post('/2fa/challenge', [PortalTwoFactorChallengeController::class, 'verifyEditor'])->name('2fa.challenge.verify');

    Route::middleware('staff.2fa')->group(function (): void {
        Route::get('/', EditorDashboardController::class)
            ->middleware('permission:question.view')
            ->name('dashboard');

        Route::middleware('permission:question.view')->group(function (): void {
            Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('/questions/create', [QuestionController::class, 'create'])
                ->middleware('permission:question.create')->name('questions.create');
            Route::post('/questions', [QuestionController::class, 'store'])
                ->middleware('permission:question.create')->name('questions.store');
            Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
            Route::put('/questions/{question}', [QuestionController::class, 'update'])
                ->middleware('permission:question.update')->name('questions.update');
            Route::get('/questions/eligible-instructors', [QuestionController::class, 'eligibleInstructors'])
                ->name('questions.eligible-instructors');
        });

        Route::get('/taxonomy', [TaxonomyController::class, 'index'])
            ->middleware('permission:taxonomy.view')->name('taxonomy.index');
        Route::get('/cms/pages', [PageController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.pages.index');
        Route::get('/media', [MediaController::class, 'index'])
            ->middleware('permission:media.view')->name('media.index');

        Route::middleware('permission:question.create|question.update|taxonomy.view')->group(function (): void {
            Route::get('/taxonomy/lookups/organ-systems', [TaxonomyLookupController::class, 'organSystems'])->name('taxonomy.lookups.organ-systems');
            Route::get('/taxonomy/lookups/subjects', [TaxonomyLookupController::class, 'subjects'])->name('taxonomy.lookups.subjects');
            Route::get('/taxonomy/lookups/lessons', [TaxonomyLookupController::class, 'lessons'])->name('taxonomy.lookups.lessons');
            Route::get('/taxonomy/lookups/tags', [TaxonomyLookupController::class, 'tags'])->name('taxonomy.lookups.tags');
        });
    });
});
