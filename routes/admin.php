<?php

declare(strict_types=1);

use App\Support\Enums\Permission;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AuditLogController;
use Modules\Admin\Http\Controllers\ClassroomOversightController;
use Modules\Admin\Http\Controllers\EditorImageUploadController;
use Modules\Admin\Http\Controllers\QuestionController;
use Modules\Admin\Http\Controllers\RoleController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Auth\Http\Controllers\AdminTwoFactorController;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;

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

        Route::post('/editor/images', EditorImageUploadController::class)
            ->middleware('throttle:30,1')
            ->name('editor.images');
    });
});
