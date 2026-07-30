<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Landing\Http\Controllers\LandingController;

/*
| Public marketing (front office) pages.
| Header/footer are shared Blade components; a single responsive source
| serves both desktop and mobile.
*/

Route::controller(LandingController::class)->group(function (): void {
    Route::get('/', 'home')->name('landing.home');
    Route::get('/features', 'features')->name('landing.features');
    Route::get('/pricing', 'pricing')->name('landing.pricing');
    Route::get('/about', 'about')->name('landing.about');
    Route::get('/contact', 'contact')->name('landing.contact');
    Route::get('/faq', 'faq')->name('landing.faq');
});
