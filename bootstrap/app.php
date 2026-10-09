<?php

use App\Http\Middleware\AccesoVendedor;
use App\Http\Middleware\BloquearSinLicencia;
use App\Http\Middleware\ExigirModulo;
use App\Http\Middleware\ExigirPermiso;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias para usar ->middleware('permiso:CODIGO') en las rutas
        $middleware->alias([
            'permiso' => ExigirPermiso::class,
            'modulo' => ExigirModulo::class,
            'licencia' => BloquearSinLicencia::class,
            'vendedor' => AccesoVendedor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
