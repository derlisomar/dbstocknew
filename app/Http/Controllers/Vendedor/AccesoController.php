<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AccesoController extends Controller
{
    public function formulario(Request $request)
    {
        abort_unless(config('vendedor.clave_hash'), 404);

        if ((int) $request->session()->get('vendedor_hasta', 0) >= time()) {
            return redirect()->route('vendedor.resumen');
        }

        return view('vendedor.login');
    }

    public function entrar(Request $request)
    {
        abort_unless(config('vendedor.clave_hash'), 404);

        $datos = $request->validate(['clave' => ['required', 'string', 'max:200']]);
        $llave = 'vendedor-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $seg = RateLimiter::availableIn($llave);

            return back()->with('error', "Demasiados intentos. Esperá {$seg} segundos.");
        }

        if (! Hash::check($datos['clave'], (string) config('vendedor.clave_hash'))) {
            RateLimiter::hit($llave, 60);
            Log::warning('Intento fallido de acceso al panel del vendedor', ['ip' => $request->ip()]);

            return back()->with('error', 'La clave no es correcta.');
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();
        $request->session()->put('vendedor_hasta', time() + 60 * (int) config('vendedor.minutos_sesion', 60));
        AuditoriaService::registrar('VENDEDOR_ENTRADA', null, null, ['por' => 'vendedor']);

        return redirect()->route('vendedor.resumen');
    }

    public function salir(Request $request)
    {
        $request->session()->forget('vendedor_hasta');

        return redirect()->route('vendedor.login')->with('success', 'Saliste del panel.');
    }
}
