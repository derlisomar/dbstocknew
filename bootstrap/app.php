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
        // Formulario público de demo: no hay sesión de usuario que proteger y la página puede venir de caché o de otro dominio.
        // Lo cuidan el campo trampa, el límite por IP y el tope de demos.
        $middleware->validateCsrfTokens(except: ['demo']);

        $middleware->web(append: [\App\Http\Middleware\SepararDominios::class]);

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
