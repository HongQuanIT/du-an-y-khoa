<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Auth — web routes (login/register/password screens).
| UI only for now; form submission handling will be wired later.
*/

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth::login')->name('login');
    Route::view('/register', 'auth::register')->name('register');
});
