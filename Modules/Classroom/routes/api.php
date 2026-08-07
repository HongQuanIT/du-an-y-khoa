<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Classroom API stubs — web UI is primary for MVP.
*/

Route::middleware('auth:sanctum')->group(function (): void {
    // Reserved for /api/v1/classrooms (see srs/modules/44 §7).
});
