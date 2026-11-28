<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RF-GEO-09: sincronización periódica de riesgo epidemiológico barrial desde el Hub FastAPI.
Schedule::command('epidemiology:sync-risk')
    ->hourly()
    ->timezone((string) config('centinela.timezone', 'America/Argentina/Cordoba'))
    ->withoutOverlapping();
