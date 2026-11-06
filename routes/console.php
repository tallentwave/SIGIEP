<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sigiep:alertas')->dailyAt('07:30')->withoutOverlapping();
// Anonimização é irreversível: só agenda quando habilitada explicitamente.
if (config('sigiep.retention.auto')) {
    Schedule::command('sigiep:retencao')->monthlyOn(1, '03:00')->withoutOverlapping();
}
