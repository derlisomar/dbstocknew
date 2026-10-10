<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Services\DemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Página pública y pedido de demo. */
class LandingController extends Controller
{
    public function index()
    {
        if (! config('landing.activa')) {
            return redirect()->route('dashboard');
        }

        return view('landing.index');
    }

    public function demo(Request $request, DemoService $demos): JsonResponse
    {
        abort_unless(config('landing.activa'), 404);

        // Campo trampa: las personas no lo ven ni lo completan; los robots sí. Se responde igual que si todo saliera bien.
        if (filled($request->input('sitio_web'))) {
            return response()->json(['ok' => true]);
        }

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'negocio' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:40'],
        ], [
            'nombre.required' => 'Escribí tu nombre.',
            'negocio.required' => 'Escribí el nombre de tu negocio.',
            'email.required' => 'Escribí tu correo.',
            'email.email' => 'Revisá el correo, parece incompleto.',
        ]);

        try {
            $demos->solicitar($datos, $request->ip());
        } catch (NegocioException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            $codigo = Str::upper(Str::random(6));
            Log::error("Demo [{$codigo}]: ".$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'ok' => false,
                'message' => "No pudimos enviar el correo con tu acceso. Probá de nuevo en unos minutos (código {$codigo}).",
            ], 500);
        }

        return response()->json(['ok' => true]);
    }
}
