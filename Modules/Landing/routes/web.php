<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Landing\Http\Controllers\LandingController;
use Modules\Landing\Http\Controllers\RobotsController;
use Modules\Landing\Http\Controllers\SitemapController;

/*
| Public marketing (front office) pages.
| Header/footer are shared Blade components; a single responsive source
| serves both desktop and mobile.
*/

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::controller(LandingController::class)->group(function (): void {
    Route::get('/', 'home')->name('landing.home');
    Route::get('/features', 'features')->name('landing.features');
    Route::get('/pricing', 'pricing')->name('landing.pricing');
    Route::get('/about', 'about')->name('landing.about');
    Route::get('/contact', 'contact')->name('landing.contact');
    Route::get('/terms', 'terms')->name('landing.terms');
    Route::get('/privacy', 'privacy')->name('landing.privacy');
    Route::get('/faq', 'faq')->name('landing.faq');
});
