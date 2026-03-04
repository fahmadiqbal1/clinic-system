<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up radiology images older than 30 days — runs daily at 2 AM
Schedule::command('radiology:cleanup-images')->dailyAt('02:00');
