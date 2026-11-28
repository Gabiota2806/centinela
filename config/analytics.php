<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Conexión al Hub Analítico Central (FastAPI)
    |--------------------------------------------------------------------------
    |
    | Parámetros dedicados para el cliente de inferencia predictiva. Cuando el
    | bloque config('centinela.hub.*') está disponible, el cliente prioriza esa
    | configuración compartida y utiliza estos valores como respaldo seguro.
    |
    */

    'hub_url' => env('CENTINELA_HUB_URL', 'http://hub_analytics:8001'),

    'api_key' => env('CENTINELA_CAPS_API_KEY', ''),

    'timeout_seconds' => (int) env('CENTINELA_HUB_TIMEOUT', 5),

];
