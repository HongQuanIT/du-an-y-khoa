<?php

declare(strict_types=1);

use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AuditLogController;
use Modules\Admin\Http\Controllers\BillingPlanController;
use Modules\Admin\Http\Controllers\BillingSubscriptionController;
use Modules\Admin\Http\Controllers\ClassroomOversightController;
use Modules\Admin\Http\Controllers\Cms\BannerController;
use Modules\Admin\Http\Controllers\Cms\FaqController;
use Modules\Admin\Http\Controllers\Cms\MenuController;
use Modules\Admin\Http\Controllers\Cms\PageController;
use Modules\Admin\Http\Controllers\EditorImageUploadController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\RoleController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Auth\Http\Controllers\AdminTwoFactorController;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Media\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Admin panel (prefix `admin`, name `admin.`)
|--------------------------------------------------------------------------
| Auth entry is separate from the learner `/login` (same web guard/session).
| Protected pages require staff roles + confirmed 2FA session.
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

$staffRoles = implode('|', [
    Role::Admin->value,
    Role::SuperAdmin->value,
    Role::ContentEditor->value,
]);

Route::middleware(['auth', 'role:'.$staffRoles])->group(function (): void {
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
        Route::view('/', 'admin::dashboard')->name('dashboard');

        Route::middleware('permission:'.Permission::UserView->value)->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::middleware('permission:'.Permission::UserManage->value)->group(function (): void {
            Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('users.verify-email');
        });

        Route::middleware('permission:'.Permission::RoleManage->value)->group(function (): void {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
            Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions');
            Route::get('/permissions', [RoleController::class, 'permissionsCatalog'])->name('permissions.index');
        });

        Route::middleware('permission:'.Permission::AuditView->value)->group(function (): void {
            Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit/{audit}', [AuditLogController::class, 'show'])->name('audit.show');
        });

        Route::middleware('permission:'.Permission::ClassroomOversee->value)->group(function (): void {
            Route::get('/classrooms', [ClassroomOversightController::class, 'index'])->name('classrooms.index');
            Route::post('/classrooms/{classroom}/force-end', [ClassroomOversightController::class, 'forceEnd'])
                ->name('classrooms.force-end');
            Route::post('/classrooms/{classroom}/approve', [ClassroomOversightController::class, 'approve'])
                ->name('classrooms.approve');
            Route::post('/classrooms/{classroom}/reject', [ClassroomOversightController::class, 'reject'])
                ->name('classrooms.reject');
            Route::post('/classrooms/{classroom}/archive', [ClassroomOversightController::class, 'archive'])
                ->name('classrooms.archive');
        });

        Route::middleware('permission:'.Permission::QuestionView->value)->group(function (): void {
            Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
        });

        Route::middleware('permission:'.Permission::QuestionCreate->value)->group(function (): void {
            Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
            Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
        });

        Route::middleware('permission:'.Permission::QuestionUpdate->value)->group(function (): void {
            Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
            Route::post('/questions/{question}/transition', [QuestionController::class, 'transition'])
                ->name('questions.transition');
        });
        
        // --- Exams ---
        Route::get('/exams', [\Modules\Admin\Http\Controllers\ExamController::class, 'index'])->name('exams.index');
        Route::get('/exams/create', [\Modules\Admin\Http\Controllers\ExamController::class, 'create'])->name('exams.create');
        Route::post('/exams', [\Modules\Admin\Http\Controllers\ExamController::class, 'store'])->name('exams.store');
        Route::get('/exams/{exam}/edit', [\Modules\Admin\Http\Controllers\ExamController::class, 'edit'])->name('exams.edit');
        Route::put('/exams/{exam}', [\Modules\Admin\Http\Controllers\ExamController::class, 'update'])->name('exams.update');
        Route::delete('/exams/{exam}', [\Modules\Admin\Http\Controllers\ExamController::class, 'destroy'])->name('exams.destroy');

        Route::post('/editor/images', EditorImageUploadController::class)
            ->middleware('throttle:30,1')
            ->name('editor.images');

        Route::middleware('permission:'.Permission::MediaView->value)->group(function (): void {
            Route::get('/media', [MediaController::class, 'index'])->name('media.index');
            Route::get('/media/items', [MediaController::class, 'items'])->name('media.items');
            Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
        });

        Route::middleware('permission:'.Permission::MediaManage->value)->group(function (): void {
            Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
            Route::post('/media/from-url', [MediaController::class, 'storeFromUrl'])->middleware('throttle:20,1')->name('media.from-url');
            Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
            Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        });

        Route::middleware('permission:'.Permission::CmsManage->value)->group(function (): void {
            Route::redirect('/cms', '/cms/pages')->name('cms.index');
            Route::get('/cms/faq', [FaqController::class, 'index'])->name('cms.faq.index');
            Route::get('/cms/faq/create', [FaqController::class, 'create'])->name('cms.faq.create');
            Route::post('/cms/faq', [FaqController::class, 'store'])->name('cms.faq.store');
            Route::get('/cms/faq/{faq}/edit', [FaqController::class, 'edit'])->name('cms.faq.edit');
            Route::put('/cms/faq/{faq}', [FaqController::class, 'update'])->name('cms.faq.update');
            Route::delete('/cms/faq/{faq}', [FaqController::class, 'destroy'])->name('cms.faq.destroy');
            Route::post('/cms/faq/{faq}/move-up', [FaqController::class, 'moveUp'])->name('cms.faq.move-up');
            Route::post('/cms/faq/{faq}/move-down', [FaqController::class, 'moveDown'])->name('cms.faq.move-down');

            Route::get('/cms/pages', [PageController::class, 'index'])->name('cms.pages.index');
            Route::get('/cms/pages/{cmsPage}/edit', [PageController::class, 'edit'])->name('cms.pages.edit');
            Route::put('/cms/pages/{cmsPage}', [PageController::class, 'update'])->name('cms.pages.update');

            Route::get('/cms/banners', [BannerController::class, 'index'])->name('cms.banners.index');
            Route::get('/cms/banners/create', [BannerController::class, 'create'])->name('cms.banners.create');
            Route::post('/cms/banners', [BannerController::class, 'store'])->name('cms.banners.store');
            Route::get('/cms/banners/{banner}/edit', [BannerController::class, 'edit'])->name('cms.banners.edit');
            Route::put('/cms/banners/{banner}', [BannerController::class, 'update'])->name('cms.banners.update');
            Route::delete('/cms/banners/{banner}', [BannerController::class, 'destroy'])->name('cms.banners.destroy');
            Route::post('/cms/banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('cms.banners.toggle');

            Route::get('/cms/menus', [MenuController::class, 'index'])->name('cms.menus.index');
            Route::get('/cms/menus/{menu}/edit', [MenuController::class, 'edit'])->name('cms.menus.edit');
            Route::put('/cms/menus/{menu}', [MenuController::class, 'update'])->name('cms.menus.update');
        });

        Route::middleware('permission:'.Permission::BillingManage->value)->group(function (): void {
            Route::get('/billing/plans', [BillingPlanController::class, 'index'])->name('billing.plans.index');
            Route::get('/billing/subscriptions', [BillingSubscriptionController::class, 'index'])->name('billing.subscriptions.index');
            Route::get('/billing/plans/{plan}/edit', [BillingPlanController::class, 'edit'])->name('billing.plans.edit');
            Route::put('/billing/plans/{plan}', [BillingPlanController::class, 'update'])->name('billing.plans.update');
            Route::get('/billing/plans/{plan}/prices/create', [BillingPlanController::class, 'createPrice'])->name('billing.plans.prices.create');
            Route::post('/billing/plans/{plan}/prices', [BillingPlanController::class, 'storePrice'])->name('billing.plans.prices.store');
            Route::get('/billing/plan-prices/{planPrice}/edit', [BillingPlanController::class, 'editPrice'])->name('billing.plan-prices.edit');
            Route::put('/billing/plan-prices/{planPrice}', [BillingPlanController::class, 'updatePrice'])->name('billing.plan-prices.update');
            Route::delete('/billing/plan-prices/{planPrice}', [BillingPlanController::class, 'destroyPrice'])->name('billing.plan-prices.destroy');
        });
    });
});
