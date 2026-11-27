<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-GEO-02: sincronización diaria automatizada de clima (Formosa Capital) a las 06:00 AM.
Schedule::command('weather:fetch-daily')
    ->dailyAt('06:00')
    ->timezone((string) config('centinela.timezone', 'America/Argentina/Cordoba'))
    ->withoutOverlapping();
