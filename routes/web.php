<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
| The public home page ("/") and other marketing routes are registered by
| the Landing module (Modules/Landing/routes/web.php).
*/

/*
|--------------------------------------------------------------------------
| Health / readiness probes (unauthenticated, no rate limit)
|--------------------------------------------------------------------------
*/
Route::get('/health', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

/*
|--------------------------------------------------------------------------
| Domain module web routes are auto-registered by each module provider.
| Cross-cutting placeholders below keep named routes referenced by shared
| middleware resolvable before those modules ship.
|--------------------------------------------------------------------------
*/
Route::redirect('/billing/plans', '/pricing')->name('billing.plans');
