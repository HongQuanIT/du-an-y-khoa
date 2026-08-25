<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('activity:flush --limit=2000')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('audit:archive --limit=10000')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
