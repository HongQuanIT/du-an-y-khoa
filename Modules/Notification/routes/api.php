<?php

declare(strict_types=1);

/*
| Notification — API v1 routes (prefix `api/v1`, names `api.notification.*`).
| list/mark-read + preferences. See srs/modules/27.
*/

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Api\NotificationApiController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('notifications', [NotificationApiController::class, 'index'])->name('index');
    Route::post('notifications/{notification}/read', [NotificationApiController::class, 'markRead'])->name('read');
    Route::post('notifications/read-all', [NotificationApiController::class, 'markAllRead'])->name('read-all');
    Route::delete('notifications/{notification}', [NotificationApiController::class, 'destroy'])->name('destroy');
});
