<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\DashboardController;

/*
| Analytics — web routes. Add server-rendered dashboards here.
*/

Route::middleware(['auth', 'learner'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
