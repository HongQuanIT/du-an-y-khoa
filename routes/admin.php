<?php

declare(strict_types=1);

use App\Support\Enums\Permission;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AuditLogController;
use Modules\Admin\Http\Controllers\BillingGatewayController;
use Modules\Admin\Http\Controllers\BillingPaymentController;
use Modules\Admin\Http\Controllers\BillingPlanController;
use Modules\Admin\Http\Controllers\BillingSubscriptionController;
use Modules\Admin\Http\Controllers\BlueprintController;
use Modules\Admin\Http\Controllers\ClassroomOversightController;
use Modules\Admin\Http\Controllers\Cms\BannerController;
use Modules\Admin\Http\Controllers\Cms\FaqController;
use Modules\Admin\Http\Controllers\Cms\MenuController;
use Modules\Admin\Http\Controllers\Cms\PageController;
use Modules\Admin\Http\Controllers\ContactInquiryController;
use Modules\Admin\Http\Controllers\CurriculumTaxonomyController;
use Modules\Admin\Http\Controllers\DashboardController;
use Modules\Admin\Http\Controllers\EditorImageUploadController;
use Modules\Admin\Http\Controllers\ExamController;
use Modules\Admin\Http\Controllers\InstitutionController;
use Modules\Admin\Http\Controllers\LearnerCatalogController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\QuestionDuplicateController;
use Modules\Admin\Http\Controllers\QuestionExportController;
use Modules\Admin\Http\Controllers\QuestionFeedbackController;
use Modules\Admin\Http\Controllers\QuestionFlagController;
use Modules\Admin\Http\Controllers\QuestionImportController;
use Modules\Admin\Http\Controllers\QuestionReviewController;
use Modules\Admin\Http\Controllers\QuestionVersionController;
use Modules\Admin\Http\Controllers\ReportController;
use Modules\Admin\Http\Controllers\RoleController;
use Modules\Admin\Http\Controllers\SettingController;
use Modules\Admin\Http\Controllers\SupportConversationController;
use Modules\Admin\Http\Controllers\TagController;
use Modules\Admin\Http\Controllers\TaxonomyController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Admin\Support\QuestionAccess;
use Modules\Auth\Http\Controllers\AdminTwoFactorController;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Classroom\Http\Controllers\LiveMessageApiController;
use Modules\Classroom\Http\Controllers\LiveModerationController;
use Modules\Classroom\Http\Controllers\LiveRoomApiController;
use Modules\Classroom\Http\Controllers\LiveRoomController;
use Modules\Media\Http\Controllers\MediaController;
use Modules\Notification\Http\Controllers\AdminBroadcastController;
use Modules\Notification\Http\Controllers\NotificationController;
use Modules\Partner\Http\Controllers\Admin\PartnerAdminController;
use Modules\QuestionBank\Http\Controllers\TaxonomyLookupController;

/*
|--------------------------------------------------------------------------
| Admin panel (prefix `admin`, name `admin.`)
|--------------------------------------------------------------------------
| Auth entry is separate from the learner `/login` (same web guard/session).
| Protected pages require staff roles + 2FA when enabled.
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'admin::auth.login')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'storeAdmin'])
        ->middleware('throttle:auth')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroyAdmin'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'portal:admin'])->group(function (): void {
    Route::get('/2fa/setup', [AdminTwoFactorController::class, 'showSetup'])->name('2fa.setup');
    Route::post('/2fa/confirm', [AdminTwoFactorController::class, 'confirmSetup'])
        ->middleware('throttle:auth')
        ->name('2fa.confirm');
    Route::get('/2fa/recovery', [AdminTwoFactorController::class, 'showRecovery'])->name('2fa.recovery');
    Route::post('/2fa/recovery', [AdminTwoFactorController::class, 'finishRecovery'])->name('2fa.recovery.finish');
    Route::get('/2fa/challenge', [AdminTwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
    Route::post('/2fa/challenge', [AdminTwoFactorController::class, 'verifyChallenge'])
        ->middleware('throttle:auth')
        ->name('2fa.challenge.verify');

    Route::middleware('staff.2fa')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::group([], function (): void {
            Route::get('/learner-data/institutions', [InstitutionController::class, 'index'])->middleware('permission:learner_catalog.view_any')->name('institutions.index');
            Route::get('/learner-data/countries', [LearnerCatalogController::class, 'index'])->middleware('permission:learner_catalog.view_any')->defaults('catalog', 'countries')->name('countries.index');
            Route::get('/learner-data/administrative-units', [LearnerCatalogController::class, 'index'])->middleware('permission:learner_catalog.view_any')->defaults('catalog', 'administrative-units')->name('administrative-units.index');
            Route::get('/learner-data/professions', [LearnerCatalogController::class, 'index'])->middleware('permission:learner_catalog.view_any')->defaults('catalog', 'professions')->name('professions.index');
            Route::get('/learner-data/education-stages', [LearnerCatalogController::class, 'index'])->middleware('permission:learner_catalog.view_any')->defaults('catalog', 'education-stages')->name('education-stages.index');
            Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.view_any')->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show')
                ->middleware('permission:user.view')
                ->whereNumber('user');
        });

        Route::group([], function (): void {
            Route::get('/learner-data/institutions/create', [InstitutionController::class, 'create'])->middleware('permission:learner_catalog.create')->name('institutions.create');
            Route::post('/learner-data/institutions', [InstitutionController::class, 'store'])->middleware('permission:learner_catalog.create')->name('institutions.store');
            Route::get('/learner-data/institutions/{institution}/edit', [InstitutionController::class, 'edit'])->middleware('permission:learner_catalog.update')->name('institutions.edit');
            Route::put('/learner-data/institutions/{institution}', [InstitutionController::class, 'update'])->middleware('permission:learner_catalog.update')->name('institutions.update');
            Route::post('/learner-data/countries', [LearnerCatalogController::class, 'store'])->middleware('permission:learner_catalog.create')->defaults('catalog', 'countries')->name('countries.store');
            Route::put('/learner-data/countries/{item}', [LearnerCatalogController::class, 'update'])->middleware('permission:learner_catalog.update')->defaults('catalog', 'countries')->whereNumber('item')->name('countries.update');
            Route::post('/learner-data/administrative-units', [LearnerCatalogController::class, 'store'])->middleware('permission:learner_catalog.create')->defaults('catalog', 'administrative-units')->name('administrative-units.store');
            Route::put('/learner-data/administrative-units/{item}', [LearnerCatalogController::class, 'update'])->middleware('permission:learner_catalog.update')->defaults('catalog', 'administrative-units')->whereNumber('item')->name('administrative-units.update');
            Route::post('/learner-data/professions', [LearnerCatalogController::class, 'store'])->middleware('permission:learner_catalog.create')->defaults('catalog', 'professions')->name('professions.store');
            Route::put('/learner-data/professions/{item}', [LearnerCatalogController::class, 'update'])->middleware('permission:learner_catalog.update')->defaults('catalog', 'professions')->whereNumber('item')->name('professions.update');
            Route::post('/learner-data/education-stages', [LearnerCatalogController::class, 'store'])->middleware('permission:learner_catalog.create')->defaults('catalog', 'education-stages')->name('education-stages.store');
            Route::put('/learner-data/education-stages/{item}', [LearnerCatalogController::class, 'update'])->middleware('permission:learner_catalog.update')->defaults('catalog', 'education-stages')->whereNumber('item')->name('education-stages.update');
            Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:user.create')->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->middleware('permission:user.create')->name('users.store');
            Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->middleware('permission:user.role_assign')->name('users.role');
            Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:user.status_update')->name('users.status');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.password_reset')->name('users.reset-password');
        });

        Route::group([], function (): void {
            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:role.view_any')->name('roles.index');
            Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:role.create')->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role.create')->name('roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.view')->name('roles.show');
            Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:role_permission.assign')->name('roles.permissions');
            Route::get('/permissions', [RoleController::class, 'permissionsCatalog'])->middleware('permission:permission.view_any')->name('permissions.index');
        });

        Route::get('/settings', [SettingController::class, 'index'])
            ->middleware('permission:system_setting.view')
            ->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])
            ->middleware('permission:system_setting.update')
            ->name('settings.update');

        Route::get('/support', [SupportConversationController::class, 'index'])
            ->middleware('permission:support_conversation.view')
            ->name('support.index');
        Route::get('/support/badge', [SupportConversationController::class, 'badge'])
            ->middleware('permission:support_conversation.view')
            ->name('support.badge');
        Route::get('/support/{conversation}', [SupportConversationController::class, 'show'])
            ->middleware('permission:support_conversation.view')
            ->name('support.show');
        Route::post('/support/{conversation}/claim', [SupportConversationController::class, 'claim'])
            ->middleware('permission:support_conversation.assign')
            ->name('support.claim');
        Route::post('/support/{conversation}/seen', [SupportConversationController::class, 'seen'])
            ->middleware('permission:support_conversation.view')
            ->name('support.seen');
        Route::post('/support/{conversation}/messages', [SupportConversationController::class, 'message'])
            ->middleware('permission:support_conversation.reply')
            ->name('support.messages.store');
        Route::post('/support/{conversation}/resolve', [SupportConversationController::class, 'resolve'])
            ->middleware('permission:support_conversation.resolve')
            ->name('support.resolve');

        Route::get('/notifications/broadcast', [AdminBroadcastController::class, 'create'])
            ->middleware('permission:notification_broadcast.view')
            ->name('notifications.broadcast');
        Route::post('/notifications/broadcast', [AdminBroadcastController::class, 'store'])
            ->middleware('permission:notification_broadcast.send')
            ->name('notifications.broadcast.store');

        Route::get('/contacts', [ContactInquiryController::class, 'index'])
            ->middleware('permission:contact.view_any')
            ->name('contacts.index');
        Route::get('/contacts/{contact}', [ContactInquiryController::class, 'show'])
            ->middleware('permission:contact.view_any')
            ->name('contacts.show');
        Route::patch('/contacts/{contact}', [ContactInquiryController::class, 'update'])
            ->middleware('permission:contact.update')
            ->name('contacts.update');
        Route::post('/contacts/{contact}/claim', [ContactInquiryController::class, 'claim'])
            ->middleware('permission:contact.update')
            ->name('contacts.claim');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->middleware('permission:notification_broadcast.view')
            ->name('notifications.index');

        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware('permission:audit_log.view')
            ->name('audit.index');
        Route::get('/audit/{audit}', [AuditLogController::class, 'show'])
            ->middleware('permission:audit_log.view')
            ->name('audit.show');

        Route::middleware('permission:classroom_oversight.create_on_behalf')->group(function (): void {
            Route::get('/classrooms/create', [ClassroomOversightController::class, 'create'])->name('classrooms.create');
            Route::get('/classrooms/content/questions', [ClassroomOversightController::class, 'contentQuestions'])
                ->name('classrooms.content.questions');
            Route::post('/classrooms', [ClassroomOversightController::class, 'store'])->name('classrooms.store');
        });

        Route::middleware('permission:classroom_oversight.view_any')->group(function (): void {
            Route::get('/classrooms', [ClassroomOversightController::class, 'index'])->name('classrooms.index');
        });

        Route::middleware('permission:classroom_oversight.view_any')->group(function (): void {
            Route::get('/classrooms/{classroom}', [ClassroomOversightController::class, 'show'])
                ->name('classrooms.show');
            Route::get('/classrooms/{classroom}/live/{liveSession}', [LiveRoomController::class, 'show'])
                ->scopeBindings()
                ->name('classrooms.live');
            Route::get('/classrooms/{classroom}/live/{liveSession}/api/bootstrap', [LiveRoomApiController::class, 'bootstrap'])
                ->scopeBindings()
                ->name('classrooms.live.api.bootstrap');
            Route::post('/classrooms/{classroom}/live/{liveSession}/api/token', [LiveRoomApiController::class, 'refreshToken'])
                ->scopeBindings()
                ->name('classrooms.live.api.token');
        });

        Route::post('/classrooms/{classroom}/sessions', [ClassroomOversightController::class, 'scheduleLive'])
            ->middleware('permission:classroom_oversight.schedule')
            ->name('classrooms.sessions.store');
        Route::middleware('permission:classroom_oversight.view_any')->group(function (): void {
            Route::post('/classrooms/{classroom}/live/{liveSession}/messages', [LiveRoomController::class, 'message'])
                ->scopeBindings()
                ->name('classrooms.live.message');
            Route::post('/classrooms/{classroom}/live/{liveSession}/api/messages', [LiveMessageApiController::class, 'store'])
                ->scopeBindings()
                ->name('classrooms.live.api.messages');
            Route::post('/classrooms/{classroom}/live/{liveSession}/api/react', [LiveModerationController::class, 'react'])
                ->scopeBindings()
                ->name('classrooms.live.api.react');
        });
        Route::post('/classrooms/{classroom}/force-end', [ClassroomOversightController::class, 'forceEnd'])
            ->middleware('permission:classroom_oversight.view_any')
            ->name('classrooms.force-end');
        Route::post('/classrooms/{classroom}/approve', [ClassroomOversightController::class, 'approve'])
            ->middleware('permission:classroom_oversight.approve')
            ->name('classrooms.approve');
        Route::post('/classrooms/{classroom}/reject', [ClassroomOversightController::class, 'reject'])
            ->middleware('permission:classroom_oversight.reject')
            ->name('classrooms.reject');
        Route::post('/classrooms/{classroom}/archive', [ClassroomOversightController::class, 'archive'])
            ->middleware('permission:classroom_oversight.archive')
            ->name('classrooms.archive');

        Route::middleware('permission:question.import')->group(function (): void {
            Route::get('/questions/import', [QuestionImportController::class, 'create'])->name('questions.import');
            Route::get('/questions/import/template', [QuestionImportController::class, 'template'])->name('questions.import.template');
            Route::post('/questions/import', [QuestionImportController::class, 'store'])->name('questions.import.upload');
            Route::get('/questions/import/{batch}', [QuestionImportController::class, 'show'])->name('questions.import.show');
            Route::get('/questions/import/{batch}/errors', [QuestionImportController::class, 'errors'])->name('questions.import.errors');
            Route::post('/questions/import/{batch}/map', [QuestionImportController::class, 'map'])->name('questions.import.map');
            Route::post('/questions/import/{batch}/commit', [QuestionImportController::class, 'commit'])->name('questions.import.commit');
        });

        Route::middleware('permission:'.Permission::QuestionFlag->value)->group(function (): void {
            Route::get('/questions/flags', [QuestionFlagController::class, 'index'])->name('questions.flags.index');
            Route::get('/questions/flags/{question}', [QuestionFlagController::class, 'show'])->name('questions.flags.show');
            Route::post('/questions/flags/{question}', [QuestionFlagController::class, 'store'])->name('questions.flags.store');
        });

        Route::get('/questions/eligible-instructors', [QuestionController::class, 'eligibleInstructors'])
            ->middleware('permission:'.Permission::QuestionCreate->value.'|'.Permission::QuestionUpdate->value)
            ->name('questions.eligible-instructors');

        Route::get('/questions/create', [QuestionController::class, 'create'])
            ->middleware('permission:'.Permission::QuestionCreate->value)
            ->name('questions.create');
        Route::post('/questions', [QuestionController::class, 'store'])
            ->middleware('permission:'.Permission::QuestionCreate->value)
            ->name('questions.store');
        Route::post('/questions/{question}/clone', [QuestionController::class, 'clone'])
            ->middleware('permission:question.clone')
            ->name('questions.clone');

        Route::middleware('permission:'.QuestionAccess::workspacePermissionMiddleware())->group(function (): void {
            Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
        });
        Route::get('/questions/export', QuestionExportController::class)
            ->middleware('permission:question.export')
            ->name('questions.export');
        Route::get('/question-feedback', [QuestionFeedbackController::class, 'index'])
            ->middleware('permission:question_feedback.view_any')
            ->name('question-feedback.index');
        Route::middleware('permission:'.QuestionAccess::workspacePermissionMiddleware())->group(function (): void {
            Route::get('/questions/{question}', [QuestionController::class, 'edit']);
            Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
            Route::get('/questions/{question}/duplicates', [QuestionDuplicateController::class, 'show'])
                ->name('questions.duplicates.show');
            Route::get('/questions/{question}/stats', [QuestionController::class, 'stats'])->name('questions.stats');
        });
        Route::post('/questions/{question}/check-duplicates', [QuestionDuplicateController::class, 'check'])
            ->middleware('permission:question.update|question.publish')
            ->name('questions.check-duplicates');
        Route::middleware('permission:question_version.view')->group(function (): void {
            Route::get('/questions/{question}/versions', [QuestionVersionController::class, 'index'])
                ->name('questions.versions.index');
        });

        Route::put('/questions/{question}', [QuestionController::class, 'update'])
            ->middleware('permission:'.Permission::QuestionUpdate->value)
            ->name('questions.update');
        Route::patch('/question-feedback/{feedback}/status', [QuestionFeedbackController::class, 'updateStatus'])
            ->middleware('permission:question_feedback.update')
            ->name('question-feedback.update-status');
        Route::post(
            '/questions/{question}/versions/{version}/restore',
            [QuestionVersionController::class, 'restore'],
        )->middleware('permission:question_version.restore')
            ->scopeBindings()
            ->name('questions.versions.restore');

        Route::post('/questions/{question}/transition', [QuestionController::class, 'transition'])
            ->middleware('permission:question.update|question.submit|question.publish|question.retire')
            ->name('questions.transition');

        Route::middleware('permission:'.Permission::QuestionPublish->value)->group(function (): void {
            Route::get('/question-reviews/{reviewRequest}', [QuestionReviewController::class, 'show'])
                ->name('questions.reviews.show');
            Route::post('/question-reviews/{reviewRequest}/approve', [QuestionReviewController::class, 'approve'])
                ->name('questions.reviews.approve');
            Route::post('/question-reviews/{reviewRequest}/reject', [QuestionReviewController::class, 'reject'])
                ->name('questions.reviews.reject');
        });

        Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])
            ->middleware('permission:'.Permission::QuestionDelete->value)
            ->name('questions.destroy');

        // JSON pickers: soạn câu hỏi (create/update) hoặc quản lý phân loại/ma trận (topic.view).
        Route::middleware('permission:'.Permission::QuestionCreate->value.'|'.Permission::QuestionUpdate->value.'|taxonomy.view|blueprint.view|curriculum.view')->group(function (): void {
            Route::get('/taxonomy/lookups/blueprints', [TaxonomyLookupController::class, 'blueprints'])->name('taxonomy.lookups.blueprints');
            Route::get('/taxonomy/lookups/blueprints/{blueprint}/sections', [TaxonomyLookupController::class, 'blueprintSections'])->name('taxonomy.lookups.sections');
            Route::get('/taxonomy/lookups/sections/{section}/core-topics', [TaxonomyLookupController::class, 'coreClinicalTopics'])->name('taxonomy.lookups.core-topics');
            Route::get('/taxonomy/lookups/core-topics/search', [TaxonomyLookupController::class, 'searchCoreClinicalTopics'])->name('taxonomy.lookups.core-topics.search');
            Route::get('/taxonomy/lookups/organ-systems', [TaxonomyLookupController::class, 'organSystems'])->name('taxonomy.lookups.organ-systems');
            Route::get('/taxonomy/lookups/subjects', [TaxonomyLookupController::class, 'subjects'])->name('taxonomy.lookups.subjects');
            Route::get('/taxonomy/lookups/lessons', [TaxonomyLookupController::class, 'lessons'])->name('taxonomy.lookups.lessons');
            Route::get('/taxonomy/lookups/tags', [TaxonomyLookupController::class, 'tags'])->name('taxonomy.lookups.tags');
        });

        Route::middleware('permission:taxonomy.view')->group(function (): void {
            Route::get('/taxonomy', [TaxonomyController::class, 'index'])->name('taxonomy.index');
        });
        Route::middleware('permission:blueprint.view')->group(function (): void {
            Route::get('/blueprints', [BlueprintController::class, 'index'])->name('blueprints.index');
        });
        Route::middleware('permission:curriculum.view')->group(function (): void {
            Route::get('/categories', [CurriculumTaxonomyController::class, 'index'])->name('curriculum.index');
            Route::redirect('/curriculum', '/admin/categories', 301);
        });
        Route::middleware('permission:tag.view')->group(function (): void {
            Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        });

        Route::middleware('permission:blueprint.create')->group(function (): void {
            Route::get('/blueprints/create', [BlueprintController::class, 'create'])->name('blueprints.create');
            Route::post('/blueprints', [BlueprintController::class, 'store'])->name('blueprints.store');
            Route::post('/blueprints/{blueprint}/sections', [BlueprintController::class, 'storeSection'])->name('blueprints.sections.store');
            Route::post('/blueprint-sections/{section}/core-topics', [BlueprintController::class, 'storeCoreTopic'])->name('blueprint-sections.core-topics.store');
            Route::put('/core-clinical-topics/{topic}/medical-nodes', [BlueprintController::class, 'syncCoreTopicMedicalNodes'])->name('core-clinical-topics.medical-nodes.sync');
        });
        Route::middleware('permission:curriculum.create')->group(function (): void {
            Route::post('/categories/organ-systems', [CurriculumTaxonomyController::class, 'storeOrganSystem'])->name('curriculum.organ-systems.store');
            Route::post('/categories/subjects', [CurriculumTaxonomyController::class, 'storeSubject'])->name('curriculum.subjects.store');
            Route::post('/categories/lessons', [CurriculumTaxonomyController::class, 'storeLesson'])->name('curriculum.lessons.store');
        });
        Route::middleware('permission:tag.create')->group(function (): void {
            Route::get('/tags/create', [TagController::class, 'create'])->name('tags.create');
            Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        });

        Route::middleware('permission:blueprint.update')->group(function (): void {
            Route::get('/blueprints/{blueprint}/edit', [BlueprintController::class, 'edit'])->name('blueprints.edit');
            Route::put('/blueprints/{blueprint}', [BlueprintController::class, 'update'])->name('blueprints.update');
        });
        Route::middleware('permission:curriculum.update')->group(function (): void {
            Route::put('/categories/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'updateOrganSystem'])->name('curriculum.organ-systems.update');
            Route::put('/categories/subjects/{subject}', [CurriculumTaxonomyController::class, 'updateSubject'])->name('curriculum.subjects.update');
            Route::put('/categories/lessons/{lesson}', [CurriculumTaxonomyController::class, 'updateLesson'])->name('curriculum.lessons.update');
            Route::post('/categories/lessons/{lesson}/subjects', [CurriculumTaxonomyController::class, 'attachLessonSubject'])->name('curriculum.lessons.subjects.attach');
            Route::delete('/categories/lessons/{lesson}/subjects/{subject}', [CurriculumTaxonomyController::class, 'detachLessonSubject'])->name('curriculum.lessons.subjects.detach');
            Route::post('/categories/lessons/{lesson}/organ-systems', [CurriculumTaxonomyController::class, 'attachLessonOrganSystem'])->name('curriculum.lessons.organ-systems.attach');
            Route::delete('/categories/lessons/{lesson}/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'detachLessonOrganSystem'])->name('curriculum.lessons.organ-systems.detach');
        });
        Route::middleware('permission:tag.update')->group(function (): void {
            Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->name('tags.edit');
            Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        });

        Route::middleware('permission:curriculum.delete')->group(function (): void {
            Route::delete('/categories/organ-systems/{organSystem}', [CurriculumTaxonomyController::class, 'destroyOrganSystem'])->name('curriculum.organ-systems.destroy');
            Route::delete('/categories/subjects/{subject}', [CurriculumTaxonomyController::class, 'destroySubject'])->name('curriculum.subjects.destroy');
            Route::delete('/categories/lessons/{lesson}', [CurriculumTaxonomyController::class, 'destroyLesson'])->name('curriculum.lessons.destroy');
        });

        Route::delete('/blueprints/{blueprint}', [BlueprintController::class, 'destroy'])
            ->middleware('permission:blueprint.delete')
            ->name('blueprints.destroy');

        Route::delete('/blueprint-sections/{section}', [BlueprintController::class, 'destroySection'])
            ->middleware('permission:blueprint.delete')
            ->name('blueprint-sections.destroy');

        Route::delete('/core-clinical-topics/{topic}', [BlueprintController::class, 'destroyCoreTopic'])
            ->middleware('permission:blueprint.delete')
            ->name('core-clinical-topics.destroy');

        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])
            ->middleware('permission:tag.delete')
            ->name('tags.destroy');

        // --- Exams ---
        Route::middleware('permission:exam.create|exam.update')->group(function (): void {
            Route::get('/exams/questions/search', [ExamController::class, 'searchQuestions'])->name('exams.questions.search');
            Route::get('/exams/topic-eligibility', [ExamController::class, 'topicEligibility'])->name('exams.topic-eligibility');
        });
        Route::get('/exams', [ExamController::class, 'index'])
            ->middleware('permission:exam.view_any')
            ->name('exams.index');
        Route::get('/exams/create', [ExamController::class, 'create'])
            ->middleware('permission:exam.create')
            ->name('exams.create');
        Route::post('/exams', [ExamController::class, 'store'])
            ->middleware('permission:exam.create')
            ->name('exams.store');
        Route::get('/exams/{exam}/edit', [ExamController::class, 'edit'])
            ->middleware('permission:exam.update')
            ->name('exams.edit');
        Route::put('/exams/{exam}', [ExamController::class, 'update'])
            ->middleware('permission:exam.update')
            ->name('exams.update');
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])
            ->middleware('permission:exam.delete')
            ->name('exams.destroy');

        Route::post('/editor/images', EditorImageUploadController::class)
            ->middleware([
                'permission:media.upload',
                'throttle:30,1',
            ])
            ->name('editor.images');

        Route::middleware('permission:media.view')->group(function (): void {
            Route::get('/media', [MediaController::class, 'index'])->name('media.index');
            Route::get('/media/items', [MediaController::class, 'items'])->name('media.items');
            Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
        });

        Route::post('/media', [MediaController::class, 'store'])
            ->middleware(['permission:media.upload', 'throttle:30,1'])
            ->name('media.store');
        Route::post('/media/from-url', [MediaController::class, 'storeFromUrl'])
            ->middleware(['permission:media.import', 'throttle:20,1'])
            ->name('media.from-url');
        Route::put('/media/{media}', [MediaController::class, 'update'])
            ->middleware('permission:media.update')
            ->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])
            ->middleware('permission:media.delete')
            ->name('media.destroy');

        Route::redirect('/cms', '/admin/cms/pages')
            ->middleware('permission:cms_page.view_any')
            ->name('cms.index');
        Route::get('/cms/faq', [FaqController::class, 'index'])
            ->middleware('permission:cms_faq.view')
            ->name('cms.faq.index');
        Route::get('/cms/faq/create', [FaqController::class, 'create'])
            ->middleware('permission:cms_faq.create')
            ->name('cms.faq.create');
        Route::post('/cms/faq', [FaqController::class, 'store'])
            ->middleware('permission:cms_faq.create')
            ->name('cms.faq.store');
        Route::get('/cms/faq/{faq}/edit', [FaqController::class, 'edit'])
            ->middleware('permission:cms_faq.update')
            ->name('cms.faq.edit');
        Route::put('/cms/faq/{faq}', [FaqController::class, 'update'])
            ->middleware('permission:cms_faq.update')
            ->name('cms.faq.update');
        Route::delete('/cms/faq/{faq}', [FaqController::class, 'destroy'])
            ->middleware('permission:cms_faq.delete')
            ->name('cms.faq.destroy');
        Route::post('/cms/faq/{faq}/move-up', [FaqController::class, 'moveUp'])
            ->middleware('permission:cms_faq.reorder')
            ->name('cms.faq.move-up');
        Route::post('/cms/faq/{faq}/move-down', [FaqController::class, 'moveDown'])
            ->middleware('permission:cms_faq.reorder')
            ->name('cms.faq.move-down');

        Route::get('/cms/pages', [PageController::class, 'index'])
            ->middleware('permission:cms_page.view_any')
            ->name('cms.pages.index');
        Route::get('/cms/pages/{cmsPage}/edit', [PageController::class, 'edit'])
            ->middleware('permission:cms_page.update')
            ->name('cms.pages.edit');
        Route::put('/cms/pages/{cmsPage}', [PageController::class, 'update'])
            ->middleware('permission:cms_page.update')
            ->name('cms.pages.update');

        Route::get('/cms/banners', [BannerController::class, 'index'])
            ->middleware('permission:cms_banner.view')
            ->name('cms.banners.index');
        Route::get('/cms/banners/create', [BannerController::class, 'create'])
            ->middleware('permission:cms_banner.create')
            ->name('cms.banners.create');
        Route::post('/cms/banners', [BannerController::class, 'store'])
            ->middleware('permission:cms_banner.create')
            ->name('cms.banners.store');
        Route::get('/cms/banners/{banner}/edit', [BannerController::class, 'edit'])
            ->middleware('permission:cms_banner.update')
            ->name('cms.banners.edit');
        Route::put('/cms/banners/{banner}', [BannerController::class, 'update'])
            ->middleware('permission:cms_banner.update')
            ->name('cms.banners.update');
        Route::delete('/cms/banners/{banner}', [BannerController::class, 'destroy'])
            ->middleware('permission:cms_banner.delete')
            ->name('cms.banners.destroy');
        Route::post('/cms/banners/{banner}/toggle', [BannerController::class, 'toggle'])
            ->middleware('permission:cms_banner.update')
            ->name('cms.banners.toggle');

        Route::get('/cms/menus', [MenuController::class, 'index'])
            ->middleware('permission:cms_menu.view')
            ->name('cms.menus.index');
        Route::get('/cms/menus/{menu}/edit', [MenuController::class, 'edit'])
            ->middleware('permission:cms_menu.update')
            ->name('cms.menus.edit');
        Route::put('/cms/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:cms_menu.update')
            ->name('cms.menus.update');

        Route::middleware('permission:report.view')->group(function (): void {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/cache/warm-all/status', [ReportController::class, 'warmAllStatusJson'])
                ->name('reports.cache.warm-all-status');
            Route::get('/reports/{category}', [ReportController::class, 'showCategory'])->name('reports.show-category');
            Route::get('/reports/{category}/{report}/refresh-status', [ReportController::class, 'refreshStatus'])
                ->name('reports.refresh-status');
            Route::get('/reports/{category}/{report}', [ReportController::class, 'showReport'])->name('reports.show');
        });
        Route::middleware('permission:report.refresh')->group(function (): void {
            Route::post('/reports/cache/warm-all', [ReportController::class, 'queueWarmAll'])
                ->name('reports.cache.warm-all');
            Route::post('/reports/cache/warm-all/reset', [ReportController::class, 'resetWarmAllStatus'])
                ->name('reports.cache.warm-all-reset');
            Route::post('/reports/{category}/{report}/refresh', [ReportController::class, 'refresh'])
                ->name('reports.refresh');
        });

        Route::middleware('permission:report_schedule.update')->group(function (): void {
            Route::post('/reports/schedules/{schedule}/toggle', [ReportController::class, 'toggleSchedule'])
                ->name('reports.schedules.toggle');
            Route::post('/reports/schedules/{schedule}/toggle-email', [ReportController::class, 'toggleScheduleEmail'])
                ->name('reports.schedules.toggle-email');
        });
        Route::post('/reports/schedules/{schedule}/destroy', [ReportController::class, 'destroySchedule'])
            ->middleware('permission:report_schedule.delete')
            ->name('reports.schedules.destroy');
        Route::post('/reports/{category}/{report}/schedules', [ReportController::class, 'storeSchedule'])
            ->middleware('permission:report_schedule.create')
            ->name('reports.schedules.store');
        Route::middleware('permission:report.export')->group(function (): void {
            Route::get('/reports/{category}/{report}/export', [ReportController::class, 'export'])->name('reports.export');
        });

        Route::get('/billing/plans', [BillingPlanController::class, 'index'])
            ->middleware('permission:billing_plan.view')
            ->name('billing.plans.index');
        Route::get('/billing/subscriptions', [BillingSubscriptionController::class, 'index'])
            ->middleware('permission:billing_subscription.view')
            ->name('billing.subscriptions.index');
        Route::get('/billing/payments', [BillingPaymentController::class, 'index'])
            ->middleware('permission:billing_payment.view')
            ->name('billing.payments.index');
        Route::get('/billing/gateways', [BillingGatewayController::class, 'index'])
            ->middleware('permission:billing_gateway.view')
            ->name('billing.gateways.index');
        Route::put('/billing/gateways', [BillingGatewayController::class, 'update'])
            ->middleware('permission:billing_gateway.update')
            ->name('billing.gateways.update');
        Route::get('/billing/plans/{plan}/edit', [BillingPlanController::class, 'edit'])
            ->middleware('permission:billing_plan.update')
            ->name('billing.plans.edit');
        Route::put('/billing/plans/{plan}', [BillingPlanController::class, 'update'])
            ->middleware('permission:billing_plan.update')
            ->name('billing.plans.update');
        Route::get('/billing/plans/{plan}/prices/create', [BillingPlanController::class, 'createPrice'])
            ->middleware('permission:billing_price.create')
            ->name('billing.plans.prices.create');
        Route::post('/billing/plans/{plan}/prices', [BillingPlanController::class, 'storePrice'])
            ->middleware('permission:billing_price.create')
            ->name('billing.plans.prices.store');
        Route::get('/billing/plan-prices/{planPrice}/edit', [BillingPlanController::class, 'editPrice'])
            ->middleware('permission:billing_price.update')
            ->name('billing.plan-prices.edit');
        Route::put('/billing/plan-prices/{planPrice}', [BillingPlanController::class, 'updatePrice'])
            ->middleware('permission:billing_price.update')
            ->name('billing.plan-prices.update');
        Route::delete('/billing/plan-prices/{planPrice}', [BillingPlanController::class, 'destroyPrice'])
            ->middleware('permission:billing_price.delete')
            ->name('billing.plan-prices.destroy');

        Route::get('/partners', [PartnerAdminController::class, 'index'])
            ->middleware('permission:partner.view_any')
            ->name('partners.index');
        Route::get('/partners/{partner}', [PartnerAdminController::class, 'show'])
            ->middleware('permission:partner.view')
            ->name('partners.show');
        Route::post('/partners/{partner}/codes', [PartnerAdminController::class, 'storeCode'])
            ->middleware('permission:partner_code.create')
            ->name('partners.codes.store');
        Route::put('/partners/{partner}/codes/{inviteCode}', [PartnerAdminController::class, 'updateCode'])
            ->middleware('permission:partner_code.update')
            ->name('partners.codes.update');
        Route::post('/partners/{partner}/codes/{inviteCode}/toggle', [PartnerAdminController::class, 'toggleCode'])
            ->middleware('permission:partner_code.update')
            ->name('partners.codes.toggle');

        Route::get('/partners-payouts', [PartnerAdminController::class, 'payoutsIndex'])
            ->middleware('permission:partner_payout.view')
            ->name('partners.payouts.index');
        Route::post('/partners-payouts', [PartnerAdminController::class, 'payoutsStore'])
            ->middleware('permission:partner_payout.create')
            ->name('partners.payouts.store');
        Route::post('/partners-payouts/{payout}/mark-paid', [PartnerAdminController::class, 'payoutsMarkPaid'])
            ->middleware('permission:partner_payout.mark_paid')
            ->name('partners.payouts.mark-paid');
    });
});
