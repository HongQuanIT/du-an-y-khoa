<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\BlueprintController;
use Modules\Admin\Http\Controllers\Cms\BannerController;
use Modules\Admin\Http\Controllers\Cms\FaqController;
use Modules\Admin\Http\Controllers\Cms\MenuController;
use Modules\Admin\Http\Controllers\Cms\PageController;
use Modules\Admin\Http\Controllers\CurriculumTaxonomyController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\QuestionExportController;
use Modules\Admin\Http\Controllers\QuestionImportController;
use Modules\Admin\Http\Controllers\TagController;
use Modules\Admin\Http\Controllers\TaxonomyController;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\PortalTwoFactorChallengeController;
use Modules\Editor\Http\Controllers\EditorDashboardController;
use Modules\Editor\Http\Controllers\EditorProfileController;
use Modules\Editor\Http\Controllers\MediaController;
use Modules\Editor\Http\Middleware\EnsureEditorViewPermission;
use Modules\Notification\Http\Controllers\NotificationController;
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

    Route::middleware(['staff.2fa', EnsureEditorViewPermission::class])->group(function (): void {
        Route::get('/', EditorDashboardController::class)
            ->middleware('permission:editor_dashboard.view')
            ->name('dashboard');
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('permission:editor_notification.view')
            ->name('notifications.index');
        Route::get('/profile', [EditorProfileController::class, 'show'])->middleware('permission:editor_profile.view')->name('profile.show');
        Route::put('/profile', [EditorProfileController::class, 'update'])->middleware('permission:editor_profile.update')->name('profile.update');
        Route::put('/profile/password', [EditorProfileController::class, 'updatePassword'])->middleware('permission:editor_profile.password_update')->name('profile.password');
        Route::put('/profile/appearance', [EditorProfileController::class, 'updateAppearance'])->middleware('permission:editor_profile.update')->name('profile.appearance');
        Route::put('/profile/avatar', [EditorProfileController::class, 'updateAvatar'])->middleware('permission:editor_profile.avatar_update')->name('profile.avatar');
        Route::delete('/profile/avatar', [EditorProfileController::class, 'destroyAvatar'])->middleware('permission:editor_profile.avatar_update')->name('profile.avatar.destroy');
        Route::middleware('permission:editor_profile.two_factor_toggle')->group(function (): void {
            Route::get('/profile/2fa/setup', [EditorProfileController::class, 'showTwoFactorSetup'])->name('profile.2fa.setup');
            Route::post('/profile/2fa/confirm', [EditorProfileController::class, 'confirmTwoFactorSetup'])->middleware('throttle:auth')->name('profile.2fa.confirm');
            Route::get('/profile/2fa/recovery', [EditorProfileController::class, 'showTwoFactorRecovery'])->name('profile.2fa.recovery');
            Route::post('/profile/2fa/recovery', [EditorProfileController::class, 'finishTwoFactorRecovery'])->name('profile.2fa.recovery.finish');
            Route::delete('/profile/2fa', [EditorProfileController::class, 'disableTwoFactor'])->middleware('throttle:auth')->name('profile.2fa.disable');
        });

        Route::middleware('permission:editor_question.view')->group(function (): void {
            Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('/questions/create', [QuestionController::class, 'create'])
                ->middleware('permission:editor_question.create')->name('questions.create');
            Route::post('/questions', [QuestionController::class, 'store'])
                ->middleware('permission:editor_question.create')->name('questions.store');
            Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
            Route::get('/questions/{question}/compare', [QuestionController::class, 'compare'])->name('questions.compare');
            Route::get('/questions/{question}/stats', [QuestionController::class, 'stats'])->name('questions.stats');
            Route::put('/questions/{question}', [QuestionController::class, 'update'])
                ->middleware('permission:editor_question.update')->name('questions.update');
            Route::post('/questions/{question}/transition', [QuestionController::class, 'transition'])
                ->middleware('permission:editor_question.submit|editor_question.update')->name('questions.transition');
            Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])
                ->middleware('permission:editor_question.delete')->name('questions.destroy');
            Route::get('/questions/eligible-instructors', [QuestionController::class, 'eligibleInstructors'])
                ->name('questions.eligible-instructors');
            Route::match(['get', 'post'], '/questions/export', QuestionExportController::class)
                ->middleware('permission:editor_question.export')->name('questions.export');
            Route::middleware('permission:editor_question.import')->group(function (): void {
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
            ->middleware('permission:editor_taxonomy.view')->name('taxonomy.index');
        Route::get('/cms/pages', [PageController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.pages.index');
        Route::get('/cms/pages/{cmsPage}/edit', [PageController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.pages.edit');
        Route::put('/cms/pages/{cmsPage}', [PageController::class, 'update'])
            ->middleware('permission:cms.update')->name('cms.pages.update');
        Route::get('/cms/faq', [FaqController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.faq.index');
        Route::get('/cms/faq/create', [FaqController::class, 'create'])
            ->middleware('permission:cms.create')->name('cms.faq.create');
        Route::post('/cms/faq', [FaqController::class, 'store'])
            ->middleware('permission:cms.create')->name('cms.faq.store');
        Route::get('/cms/faq/{faq}/edit', [FaqController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.faq.edit');
        Route::put('/cms/faq/{faq}', [FaqController::class, 'update'])
            ->middleware('permission:cms.update')->name('cms.faq.update');
        Route::delete('/cms/faq/{faq}', [FaqController::class, 'destroy'])
            ->middleware('permission:cms.delete')->name('cms.faq.destroy');
        Route::post('/cms/faq/{faq}/move-up', [FaqController::class, 'moveUp'])
            ->middleware('permission:cms.update')->name('cms.faq.move-up');
        Route::post('/cms/faq/{faq}/move-down', [FaqController::class, 'moveDown'])
            ->middleware('permission:cms.update')->name('cms.faq.move-down');
        Route::get('/cms/banners', [BannerController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.banners.index');
        Route::get('/cms/banners/create', [BannerController::class, 'create'])
            ->middleware('permission:cms.create')->name('cms.banners.create');
        Route::post('/cms/banners', [BannerController::class, 'store'])
            ->middleware('permission:cms.create')->name('cms.banners.store');
        Route::get('/cms/banners/{banner}/edit', [BannerController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.banners.edit');
        Route::put('/cms/banners/{banner}', [BannerController::class, 'update'])
            ->middleware('permission:cms.update')->name('cms.banners.update');
        Route::delete('/cms/banners/{banner}', [BannerController::class, 'destroy'])
            ->middleware('permission:cms.delete')->name('cms.banners.destroy');
        Route::post('/cms/banners/{banner}/toggle', [BannerController::class, 'toggle'])
            ->middleware('permission:cms.update')->name('cms.banners.toggle');
        Route::get('/cms/menus', [MenuController::class, 'index'])
            ->middleware('permission:cms.view')->name('cms.menus.index');
        Route::get('/cms/menus/{menu}/edit', [MenuController::class, 'edit'])
            ->middleware('permission:cms.update')->name('cms.menus.edit');
        Route::put('/cms/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:cms.update')->name('cms.menus.update');
        Route::get('/media', [MediaController::class, 'index'])
            ->middleware('permission:editor_media.view')->name('media.index');
        Route::get('/media/items', [MediaController::class, 'items'])
            ->middleware('permission:editor_media.view')->name('media.items');
        Route::get('/media/{media}', [MediaController::class, 'show'])
            ->middleware('permission:editor_media.view')->name('media.show');
        Route::post('/media', [MediaController::class, 'store'])
            ->middleware(['permission:editor_media.upload', 'throttle:30,1'])->name('media.store');
        Route::post('/media/from-url', [MediaController::class, 'storeFromUrl'])
            ->middleware(['permission:editor_media.upload', 'throttle:20,1'])->name('media.from-url');
        Route::put('/media/{media}', [MediaController::class, 'update'])
            ->middleware('permission:editor_media.update')->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])
            ->middleware('permission:editor_media.delete')->name('media.destroy');

        Route::middleware('permission:editor_taxonomy.view')->group(function (): void {
            Route::get('/blueprints', [BlueprintController::class, 'index'])->middleware('permission:editor_blueprint.view')->name('blueprints.index');
            Route::get('/blueprints/create', [BlueprintController::class, 'create'])->middleware('permission:editor_blueprint.create')->name('blueprints.create');
            Route::post('/blueprints', [BlueprintController::class, 'store'])->middleware('permission:editor_blueprint.create')->name('blueprints.store');
            Route::post('/blueprints/{blueprint}/sections', [BlueprintController::class, 'storeSection'])->middleware('permission:editor_blueprint.create')->name('blueprints.sections.store');
            Route::post('/blueprint-sections/{section}/core-topics', [BlueprintController::class, 'storeCoreTopic'])->middleware('permission:editor_blueprint.create')->name('blueprint-sections.core-topics.store');
            Route::get('/blueprints/{blueprint}/edit', [BlueprintController::class, 'edit'])->middleware('permission:editor_blueprint.update')->name('blueprints.edit');
            Route::put('/blueprints/{blueprint}', [BlueprintController::class, 'update'])->middleware('permission:editor_blueprint.update')->name('blueprints.update');
            Route::put('/blueprints/{blueprint}/weights', [BlueprintController::class, 'updateWeights'])->middleware('permission:editor_blueprint.update')->name('blueprints.weights.update');
            Route::put('/core-clinical-topics/{topic}/medical-nodes', [BlueprintController::class, 'syncCoreTopicMedicalNodes'])->middleware('permission:editor_blueprint.update')->name('core-clinical-topics.medical-nodes.sync');
            Route::delete('/blueprints/{blueprint}', [BlueprintController::class, 'destroy'])->middleware('permission:editor_blueprint.delete')->name('blueprints.destroy');
            Route::delete('/blueprint-sections/{section}', [BlueprintController::class, 'destroySection'])->middleware('permission:editor_blueprint.delete')->name('blueprint-sections.destroy');
            Route::delete('/core-clinical-topics/{topic}', [BlueprintController::class, 'destroyCoreTopic'])->middleware('permission:editor_blueprint.delete')->name('core-clinical-topics.destroy');
            Route::get('/categories', [CurriculumTaxonomyController::class, 'index'])->middleware('permission:editor_curriculum.view')->name('curriculum.index');
            Route::post('/categories/organ-systems', [CurriculumTaxonomyController::class, 'storeOrganSystem'])->middleware('permission:editor_curriculum.create')->name('curriculum.organ-systems.store');
            Route::put('/categories/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'updateOrganSystem'])->middleware('permission:editor_curriculum.update')->name('curriculum.organ-systems.update');
            Route::delete('/categories/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'destroyOrganSystem'])->middleware('permission:editor_curriculum.delete')->name('curriculum.organ-systems.destroy');
            Route::post('/categories/subjects', [CurriculumTaxonomyController::class, 'storeSubject'])->middleware('permission:editor_curriculum.create')->name('curriculum.subjects.store');
            Route::put('/categories/subjects/{subject}', [CurriculumTaxonomyController::class, 'updateSubject'])->middleware('permission:editor_curriculum.update')->name('curriculum.subjects.update');
            Route::delete('/categories/subjects/{subject}', [CurriculumTaxonomyController::class, 'destroySubject'])->middleware('permission:editor_curriculum.delete')->name('curriculum.subjects.destroy');
            Route::post('/categories/subjects/{subject}/lessons', [CurriculumTaxonomyController::class, 'attachSubjectLessons'])->middleware('permission:editor_curriculum.update')->name('curriculum.subjects.lessons.attach');
            Route::delete('/categories/subjects/{subject}/lessons/{lesson}', [CurriculumTaxonomyController::class, 'detachSubjectLesson'])->middleware('permission:editor_curriculum.update')->name('curriculum.subjects.lessons.detach');
            Route::post('/categories/lessons', [CurriculumTaxonomyController::class, 'storeLesson'])->middleware('permission:editor_curriculum.create')->name('curriculum.lessons.store');
            Route::put('/categories/lessons/{lesson}', [CurriculumTaxonomyController::class, 'updateLesson'])->middleware('permission:editor_curriculum.update')->name('curriculum.lessons.update');
            Route::delete('/categories/lessons/{lesson}', [CurriculumTaxonomyController::class, 'destroyLesson'])->middleware('permission:editor_curriculum.delete')->name('curriculum.lessons.destroy');
            Route::post('/categories/lessons/{lesson}/subjects', [CurriculumTaxonomyController::class, 'attachLessonSubject'])->middleware('permission:editor_curriculum.update')->name('curriculum.lessons.subjects.attach');
            Route::delete('/categories/lessons/{lesson}/subjects/{subject}', [CurriculumTaxonomyController::class, 'detachLessonSubject'])->middleware('permission:editor_curriculum.update')->name('curriculum.lessons.subjects.detach');
            Route::post('/categories/lessons/{lesson}/organ-systems', [CurriculumTaxonomyController::class, 'attachLessonOrganSystem'])->middleware('permission:editor_curriculum.update')->name('curriculum.lessons.organ-systems.attach');
            Route::delete('/categories/lessons/{lesson}/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'detachLessonOrganSystem'])->middleware('permission:editor_curriculum.update')->name('curriculum.lessons.organ-systems.detach');
            Route::get('/tags', [TagController::class, 'index'])->middleware('permission:editor_tag.view')->name('tags.index');
            Route::get('/tags/create', [TagController::class, 'create'])->middleware('permission:editor_tag.create')->name('tags.create');
            Route::post('/tags', [TagController::class, 'store'])->middleware('permission:editor_tag.create')->name('tags.store');
            Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->middleware('permission:editor_tag.update')->name('tags.edit');
            Route::put('/tags/{tag}', [TagController::class, 'update'])->middleware('permission:editor_tag.update')->name('tags.update');
            Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:editor_tag.delete')->name('tags.destroy');
        });

        Route::middleware('permission:editor_question.create|editor_question.update|editor_taxonomy.view')->group(function (): void {
            Route::get('/taxonomy/lookups/blueprints', [TaxonomyLookupController::class, 'blueprints'])->name('taxonomy.lookups.blueprints');
            Route::get('/taxonomy/lookups/blueprints/{blueprint}/sections', [TaxonomyLookupController::class, 'blueprintSections'])->name('taxonomy.lookups.sections');
            Route::get('/taxonomy/lookups/sections/{section}/core-topics', [TaxonomyLookupController::class, 'coreClinicalTopics'])->name('taxonomy.lookups.core-topics');
            Route::get('/taxonomy/lookups/core-topics/search', [TaxonomyLookupController::class, 'searchCoreClinicalTopics'])->name('taxonomy.lookups.core-topics.search');
            Route::get('/taxonomy/lookups/organ-systems', [TaxonomyLookupController::class, 'organSystems'])->name('taxonomy.lookups.organ-systems');
            Route::get('/taxonomy/lookups/subjects', [TaxonomyLookupController::class, 'subjects'])->name('taxonomy.lookups.subjects');
            Route::get('/taxonomy/lookups/lessons', [TaxonomyLookupController::class, 'lessons'])->name('taxonomy.lookups.lessons');
            Route::get('/taxonomy/lookups/tags', [TaxonomyLookupController::class, 'tags'])->name('taxonomy.lookups.tags');
        });
    });
});
