<?php

namespace App\Http\Middleware;

use App\Services\ConfiguracionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas:  ->middleware('modulo:compras')
 * Si el módulo no está incluido en el plan del negocio, no se puede entrar aunque se escriba la dirección a mano.
 */
class ExigirModulo
{
    public function handle(Request $request, Closure $next, string ...$modulos): Response
    {
        foreach ($modulos as $modulo) {
            if (ConfiguracionService::modulo($modulo)) {
                return $next($request);
            }
        }

        $nombre = config('modulos.catalogo.'.$modulos[0].'.nombre', $modulos[0]);
        $mensaje = "«{$nombre}» no está incluido en tu plan. Consultá con tu proveedor para activarlo.";

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $mensaje], 403);
        }

        return redirect()->route('dashboard')->with('error', $mensaje);
    }
}
