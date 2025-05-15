<?php

use Illuminate\Support\Facades\Schedule;

return function (Schedule $schedule) {
    // Run file cleanup daily
    $schedule->command('files:cleanup')->daily();
};