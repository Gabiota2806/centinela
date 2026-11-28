<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nodo CAPS
    |--------------------------------------------------------------------------
    |
    | Nombre del centro de salud donde corre este nodo local (Edge). Se muestra
    | en la barra superior del layout autenticado.
    |
    */

    'node_name' => env('CENTINELA_NODE_NAME', 'CAPS · Nodo local'),

    /*
    |--------------------------------------------------------------------------
    | Código de Nodo Emisor y Hub Analítico Central
    |--------------------------------------------------------------------------
    |
    | Identificador anónimo del CAPS emisor (nunca PII de pacientes) y parámetros
    | de conexión HTTP REST hacia el microservicio FastAPI del Hub Central.
    |
    */

    'caps_code' => env('CENTINELA_CAPS_CODE', 'CAPS-CODDA-01'),

    'hub' => [
        'url' => env('CENTINELA_HUB_URL', 'http://hub_analytics:8001'),
        'api_key' => env('CENTINELA_CAPS_API_KEY', ''),
        'timeout_seconds' => (int) env('CENTINELA_HUB_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de visualización
    |--------------------------------------------------------------------------
    |
    | Las fechas se guardan en UTC (config/app.php) y se muestran al personal en
    | la hora local del CAPS. La zona IANA de Formosa es America/Argentina/Cordoba.
    |
    */

    'timezone' => env('CENTINELA_TIMEZONE', 'America/Argentina/Cordoba'),

    /*
    |--------------------------------------------------------------------------
    | Navegación lateral
    |--------------------------------------------------------------------------
    |
    | Ítems de la barra lateral. Solo se muestran los que tienen la ruta
    | registrada (Route::has) y, si declaran `can`, cuyo Gate el usuario tiene
    | (docs/specs/auth/spec.md §4). El ícono es una clave de <x-icon>.
    |
    */

    'navigation' => [
        ['label' => 'Inicio', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Admisión', 'route' => 'patients.index', 'icon' => 'clipboard-list', 'can' => 'access-admission'],
        ['label' => 'Nuevo paciente', 'route' => 'patients.create', 'icon' => 'user-plus', 'can' => 'access-admission'],
        ['label' => 'Triage MTS', 'route' => 'triage.index', 'icon' => 'activity', 'can' => 'access-triage'],
        ['label' => 'Consultas', 'route' => 'consultations.index', 'icon' => 'stethoscope', 'can' => 'access-doctor'],
        ['label' => 'Mi perfil', 'route' => 'profile.edit', 'icon' => 'user-circle'],
    ],

];
