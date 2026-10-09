<?php

return [

    /*
    | Acceso privado del vendedor del sistema (no es un usuario del negocio).
    | La clave se guarda como "hash" en el archivo .env, no en la base de datos:
    |   php artisan vendedor:clave
    | genera la línea lista para pegar. Sin esa línea el panel queda desactivado.
    */
    'clave_hash' => env('VENDEDOR_CLAVE_HASH'),

    /** Dirección del panel. Cambiala por algo que solo vos sepas, por ejemplo "panel-x7k2". */
    'ruta' => env('VENDEDOR_RUTA', 'panel-vendedor'),

    /** Minutos de sesión del panel sin actividad. */
    'minutos_sesion' => (int) env('VENDEDOR_MINUTOS', 60),

    /** Carpeta (dentro de public) donde se guarda el logo del negocio. */
    'carpeta_logo' => public_path('img/negocio'),

    /** Zona horaria para decidir vencimientos de licencia. */
    'zona' => 'America/Asuncion',

    /** Días de aviso previo al vencimiento. */
    'aviso_dias' => 7,

    /** Días de gracia por defecto después de vencer (antes de pasar a solo lectura). */
    'gracia_dias' => 5,

];
