<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GeoSpatialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API de Centinela (/api/...)
|--------------------------------------------------------------------------
|
| Se monta con middleware de sesión ('web') + 'auth' + Gate para soportar
| consumo same-origin desde el dashboard Leaflet.js devolviendo siempre
| respuestas JSON (401/403/200) conforme a bootstrap/app.php y 00_contracts.md.
|
*/

Route::prefix('v1')
    ->middleware(['web', 'auth'])
    ->group(function (): void {
        Route::get('/geo/neighborhoods', [GeoSpatialController::class, 'neighborhoods'])
            ->middleware('can:view-epidemiology')
            ->name('api.v1.geo.neighborhoods');
    });
