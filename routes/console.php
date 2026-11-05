<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Weekly Wikidata import of shared catalog data. Needs a cPanel cron entry:
 * * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('catalog:import-publishers')->weeklyOn(1, '03:00')->withoutOverlapping();
Schedule::command('catalog:import-authors')->weeklyOn(1, '03:30')->withoutOverlapping();
