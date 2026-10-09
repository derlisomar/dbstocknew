<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el panel del vendedor. No usa los usuarios del negocio: el acceso es con una clave propia
 * (hash en .env), así el Administrador del cliente no puede entrar ni cambiar su plan.
 */
class AccesoVendedor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('vendedor.clave_hash')) {
            abort(404);
        }

        $hasta = (int) $request->session()->get('vendedor_hasta', 0);
        if ($hasta < time()) {
            $request->session()->forget('vendedor_hasta');

            return redirect()->route('vendedor.login');
        }

        // Cada actividad renueva el tiempo de sesión.
        $request->session()->put('vendedor_hasta', time() + 60 * (int) config('vendedor.minutos_sesion', 60));

        return $next($request);
    }
}
