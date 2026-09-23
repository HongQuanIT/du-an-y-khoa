<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
use Modules\Editor\Http\Controllers\EditorDashboardController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\QuestionImportController;
use Modules\Admin\Http\Controllers\QuestionExportController;
use Modules\Admin\Http\Controllers\TaxonomyController;
use Modules\Admin\Http\Controllers\Cms\PageController;
use Modules\Admin\Http\Controllers\Cms\FaqController;
use Modules\Admin\Http\Controllers\Cms\BannerController;
use Modules\Admin\Http\Controllers\Cms\MenuController;
use Modules\Admin\Http\Controllers\BlueprintController;
use Modules\Admin\Http\Controllers\CurriculumTaxonomyController;
use Modules\Admin\Http\Controllers\TagController;
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
            Route::match(['get', 'post'], '/questions/export', QuestionExportController::class)
                ->middleware('permission:question.export')->name('questions.export');
            Route::middleware('permission:question.import')->group(function (): void {
                Route::get('/questions/import', [QuestionImportController::class, 'create'])->name('questions.import');
                Route::get('/questions/import/template', [QuestionImportController::class, 'template'])->name('questions.import.template');
                Route::post('/questions/import', [QuestionImportController::class, 'store'])->name('questions.import.upload');
                Route::get('/questions/import/{batch}', [QuestionImportController::class, 'show'])->name('questions.import.show');
                Route::get('/questions/import/{batch}/errors', [QuestionImportController::class, 'errors'])->name('questions.import.errors');
                Route::post('/questions/import/{batch}/map', [QuestionImportController::class, 'map'])->name('questions.import.map');
                Route::post('/questions/import/{batch}/commit', [QuestionImportController::class, 'commit'])->name('questions.import.commit');
            });
        });

        Route::get('/taxonomy', [TaxonomyController::class, 'index'])
            ->middleware('permission:taxonomy.view')->name('taxonomy.index');
        Route::get('/cms/pages', [PageController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.pages.index');
        Route::get('/cms/pages/{cmsPage}/edit', [PageController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.pages.edit');
        Route::get('/cms/faq', [FaqController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.faq.index');
        Route::get('/cms/faq/create', [FaqController::class, 'create'])
            ->middleware('permission:cms.update')->name('cms.faq.create');
        Route::get('/cms/faq/{faq}/edit', [FaqController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.faq.edit');
        Route::get('/cms/banners', [BannerController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.banners.index');
        Route::get('/cms/banners/create', [BannerController::class, 'create'])
            ->middleware('permission:cms.create')->name('cms.banners.create');
        Route::get('/cms/banners/{banner}/edit', [BannerController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.banners.edit');
        Route::get('/cms/menus', [MenuController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.menus.index');
        Route::get('/cms/menus/{menu}/edit', [MenuController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.menus.edit');
        Route::get('/media', [MediaController::class, 'index'])
            ->middleware('permission:media.view')->name('media.index');
        Route::get('/media/items', [MediaController::class, 'items'])
            ->middleware('permission:media.view')->name('media.items');
        Route::get('/media/{media}', [MediaController::class, 'show'])
            ->middleware('permission:media.view')->name('media.show');

        Route::middleware('permission:taxonomy.view')->group(function (): void {
            Route::get('/blueprints', [BlueprintController::class, 'index'])->middleware('permission:blueprint.view')->name('blueprints.index');
            Route::get('/blueprints/create', [BlueprintController::class, 'create'])->middleware('permission:blueprint.create')->name('blueprints.create');
            Route::get('/blueprints/{blueprint}/edit', [BlueprintController::class, 'edit'])->middleware('permission:blueprint.update')->name('blueprints.edit');
            Route::get('/categories', [CurriculumTaxonomyController::class, 'index'])->middleware('permission:curriculum.view')->name('curriculum.index');
            Route::get('/tags', [TagController::class, 'index'])->middleware('permission:tag.view')->name('tags.index');
            Route::get('/tags/create', [TagController::class, 'create'])->middleware('permission:tag.create')->name('tags.create');
            Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->middleware('permission:tag.update')->name('tags.edit');
        });

        Route::middleware('permission:question.create|question.update|taxonomy.view')->group(function (): void {
            Route::get('/taxonomy/lookups/organ-systems', [TaxonomyLookupController::class, 'organSystems'])->name('taxonomy.lookups.organ-systems');
            Route::get('/taxonomy/lookups/subjects', [TaxonomyLookupController::class, 'subjects'])->name('taxonomy.lookups.subjects');
            Route::get('/taxonomy/lookups/lessons', [TaxonomyLookupController::class, 'lessons'])->name('taxonomy.lookups.lessons');
            Route::get('/taxonomy/lookups/tags', [TaxonomyLookupController::class, 'tags'])->name('taxonomy.lookups.tags');
        });
    });
});
