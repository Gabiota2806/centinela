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
