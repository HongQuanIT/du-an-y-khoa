<?php

declare(strict_types=1);

use App\Support\Enums\Role;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel (prefix `admin`, name `admin.`, guarded by staff roles)
|--------------------------------------------------------------------------
| Admin module routes are auto-registered by module providers. This file
| holds the shared guard group and landing route.
*/

Route::middleware(['auth', 'role:'.Role::Admin->value.'|'.Role::SuperAdmin->value.'|'.Role::ContentEditor->value])
    ->group(function (): void {
        Route::view('/', 'welcome')->name('dashboard');
    });
