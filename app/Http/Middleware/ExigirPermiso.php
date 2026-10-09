<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas:  ->middleware('permiso:VENTAS_ANULAR')
 * Con varios códigos alcanza con tener UNO:  ->middleware('permiso:FINANZAS_VER,CAJA_ABRIR_CERRAR')
 */
class ExigirPermiso
{
    public function handle(Request $request, Closure $next, string ...$codigos): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            abort(401);
        }

        foreach ($codigos as $codigo) {
            if ($usuario->tienePermiso($codigo)) {
                return $next($request);
            }
        }

        abort(403, 'No tenés permiso para realizar esta acción.');
    }
}
