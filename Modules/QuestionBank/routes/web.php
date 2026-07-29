<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Web (Blade/Livewire) routes for the QuestionBank module.
| Add server-rendered pages here; API lives in routes/api.php.
*/

Route::middleware(['auth'])->group(function (): void {
    // Route::get('/qbank', QuestionBankPage::class)->name('qbank.index');
});
