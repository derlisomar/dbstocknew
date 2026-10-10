<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario de la base. Requiere que el servidor ejecute "php artisan schedule:run" cada minuto (cron).
Schedule::command('respaldo:base')
    ->dailyAt(config('respaldos.hora', '02:00'))
    ->withoutOverlapping()
    ->runInBackground();

// Cierra las demos vencidas de la página pública (también se hace al llegar un pedido nuevo).
Schedule::command('demo:limpiar')->dailyAt('03:00');

// Contabilidad: pone al día los asientos (solo hace algo si el módulo está preparado).
Schedule::command('contabilidad:sincronizar')->everyFiveMinutes()->withoutOverlapping();

// Latido del programador de tareas: el panel del vendedor lo usa para avisar si el cron no está corriendo.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler_latido', now()->timestamp, 3600))->everyMinute()->name('latido');
