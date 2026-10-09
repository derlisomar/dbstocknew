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
