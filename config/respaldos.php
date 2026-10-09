<?php

return [

    /*
    | Carpeta donde se guardan los respaldos (fuera de "public", nunca accesible desde internet).
    | Conviene que sea un disco distinto al de la base de datos.
    */
    'directorio' => env('BACKUP_DIR', storage_path('app/respaldos')),

    /* Cuántos días se conservan los respaldos. Los más viejos se borran solos. */
    'dias' => (int) env('BACKUP_DIAS', 14),

    /* Siempre quedan al menos estos respaldos, aunque sean más viejos que "dias". */
    'minimo' => 3,

    /* Rutas de las herramientas de PostgreSQL si no están en el PATH del servidor. */
    'pg_dump' => env('PG_DUMP_PATH', 'pg_dump'),
    'pg_restore' => env('PG_RESTORE_PATH', 'pg_restore'),

    /* Hora diaria del respaldo automático (formato HH:MM). */
    'hora' => env('BACKUP_HORA', '02:00'),
];
