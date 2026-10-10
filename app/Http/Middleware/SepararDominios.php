<?php

namespace App\Http\Middleware;

use App\Support\Dominios;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con dos dominios configurados:
 *  - dbstock.com.py      muestra solo la página pública (/ y /demo); lo demás pasa al sistema.
 *  - app.dbstock.com.py  es el sistema; su raíz lleva al inicio (o al login).
 */
class SepararDominios
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Dominios::separados()) {
            return $next($request);
        }

        $host = $request->getHost();

        if (Dominios::esWeb($host)) {
            $ruta = trim($request->path(), '/');
            if (in_array($ruta, ['', 'demo', 'up'], true)) {
                return $next($request);
            }

            return redirect()->away(Dominios::urlApp($request->getRequestUri()), 302);
        }

        if (Dominios::esApp($host)) {
            if (trim($request->path(), '/') === '') {
                return redirect('/dashboard');
            }
            if (trim($request->path(), '/') === 'demo') {
                abort(404);
            }
        }

        return $next($request);
    }
}
