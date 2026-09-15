<?php

declare(strict_types=1);

/*
| Notification — API v1 routes (prefix `api/v1`, names `api.notification.*`).
| list/mark-read + preferences. See srs/modules/27.
*/

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Api\NotificationApiController;

Route::middleware(['auth:sanctum', 'permission:notification.view'])->group(function (): void {
    Route::get('notifications', [NotificationApiController::class, 'index'])->name('index');
    Route::post('notifications/{notification}/read', [NotificationApiController::class, 'markRead'])->middleware('permission:notification.update')->name('read');
    Route::post('notifications/read-all', [NotificationApiController::class, 'markAllRead'])->middleware('permission:notification.update')->name('read-all');
    Route::delete('notifications/{notification}', [NotificationApiController::class, 'destroy'])->middleware('permission:notification.delete')->name('destroy');
});
