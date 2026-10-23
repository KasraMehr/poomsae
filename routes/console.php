<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('poomsae:draw-forms')->everyMinute()->withoutOverlapping();
Schedule::command('poomsae:prune-temporary-data')->daily()->withoutOverlapping();
Schedule::command('sanctum:prune-expired')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
