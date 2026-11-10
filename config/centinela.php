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
    | registrada (Route::has), así los módulos de próximos sprints aparecen
    | al crear su ruta. El ícono es una clave de <x-icon>.
    |
    */

    'navigation' => [
        ['label' => 'Inicio', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Mi perfil', 'route' => 'profile.edit', 'icon' => 'user-circle'],
    ],

];
