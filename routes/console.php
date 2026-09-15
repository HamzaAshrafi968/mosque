<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Audio announcements auto-delete one week after publishing: the scheduler
// removes expired rows (and their audio files) every hour.
Schedule::command('announcements:purge-expired')->hourly()->withoutOverlapping();
