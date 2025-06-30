<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('files:cleanup')->daily();



// Kode ini akan menjadwalkan command 'attendance:auto-checkout'
// untuk berjalan setiap hari pada pukul 00:05.
Schedule::command('attendance:auto-checkout')->dailyAt('00:05');
