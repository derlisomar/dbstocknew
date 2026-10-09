<?php

namespace App\Http\Middleware;

use App\Services\LicenciaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando la licencia está en SOLO_LECTURA se puede mirar todo (pedidos GET) pero no registrar nada.
 * Quedan permitidos cerrar sesión y cerrar una caja abierta (para no dejar dinero sin cerrar).
 */
class BloquearSinLicencia
{
    private const PERMITIDAS = ['logout', 'finanzas.cerrarCaja'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs(...self::PERMITIDAS)) {
            return $next($request);
        }

        $estado = LicenciaService::estado();
        if (! $estado['bloquea']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $estado['mensaje']], 423);
        }

        return back()->with('error', $estado['mensaje']);
    }
}
