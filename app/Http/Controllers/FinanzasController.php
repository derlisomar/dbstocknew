<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Services\AuditoriaService;
use App\Services\CajaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinanzasController extends Controller
{
    public function index()
    {
        $cajas = Caja::with('sucursal')->orderBy('suc_id', 'asc')->get();
        return view('finanzas.index', compact('cajas'));
    }

    // Mostrar el formulario de apertura de caja
    public function aperturaForm()
    {
        $cajas = Caja::with('sucursal')->where('caj_activa', true)->get();
        return view('finanzas.apertura', compact('cajas'));
    }

    // Guardar la apertura de caja (crear sesión abierta)
    public function aperturaStore(Request $request)
    {
        $request->validate([
            'caj_id' => 'required|exists:cajas,caj_id',
            'ses_monto_inicial_gs' => 'required|numeric|min:0',
            'ses_monto_inicial_usd' => 'nullable|numeric|min:0',
            'ses_monto_inicial_brl' => 'nullable|numeric|min:0',
        ]);

        $usuario = $request->user();

        try {
            DB::transaction(function () use ($request, $usuario) {
                // Se bloquea la caja: dos aperturas simultáneas se ponen en fila.
                $caja = Caja::where('caj_id', $request->caj_id)->lockForUpdate()->first();

                if (! $caja->caj_activa) {
                    throw new NegocioException('Esa caja está desactivada.');
                }

                if (CajaSesion::where('caj_id', $caja->caj_id)->where('ses_estado', 'ABIERTA')->exists()) {
                    throw new NegocioException('Esta caja ya tiene una sesión abierta actualmente.');
                }

                if (CajaSesion::where('usu_id', $usuario->usu_id)->where('ses_estado', 'ABIERTA')->exists()) {
                    throw new NegocioException('Ya tenés una caja abierta. Cerrala antes de abrir otra.');
                }

                $sesion = CajaSesion::create([
                    'caj_id' => $caja->caj_id,
                    'usu_id' => $usuario->usu_id,
                    'ses_fecha_apertura' => now(),
                    'ses_monto_inicial_gs' => $request->ses_monto_inicial_gs,
                    'ses_monto_inicial_usd' => $request->ses_monto_inicial_usd ?? 0,
                    'ses_monto_inicial_brl' => $request->ses_monto_inicial_brl ?? 0,
                    'ses_estado' => 'ABIERTA',
                ]);

                // El efectivo con el que arranca el turno pasa a ser el saldo físico de la caja. Como no hay otra sesión
                // abierta, el saldo anterior es solo un resto de turnos viejos: se reemplaza (el cierre lo deja en cero).
                $caja->update([
                    'caj_saldo_gs' => (float) $request->ses_monto_inicial_gs,
                    'caj_saldo_usd' => (float) ($request->ses_monto_inicial_usd ?? 0),
                    'caj_saldo_brl' => (float) ($request->ses_monto_inicial_brl ?? 0),
                ]);

                AuditoriaService::registrar('CAJA_APERTURA', 'caja_sesiones', $sesion->ses_id, [
                    'caja' => $caja->caj_id,
                    'inicial_gs' => (float) $request->ses_monto_inicial_gs,
                ]);
            });
        } catch (NegocioException $e) {
            return back()->withErrors(['caj_id' => $e->getMessage()])->withInput();
        } catch (QueryException $e) {
            // Respaldo: el índice único de la base también impide dos sesiones abiertas en la misma caja.
            Log::warning('Apertura de caja rechazada por la base', ['error' => $e->getMessage()]);

            return back()->withErrors(['caj_id' => 'Esta caja ya tiene una sesión abierta actualmente.'])->withInput();
        }

        return redirect()->route('finanzas.index')->with('success', '¡Caja aperturada con éxito!');
    }

    public function movimientos()
    {
        $cajas = Caja::with('sucursal')->where('caj_activa', true)->get();
        $movimientos = CajaMovimiento::with('sesion.caja')->orderBy('mov_id', 'desc')->take(50)->get();
        return view('finanzas.movimientos', compact('cajas', 'movimientos'));
    }

    public function transferir(Request $request, CajaService $cajas)
    {
        $request->validate([
            'ses_id_origen' => 'required|exists:caja_sesiones,ses_id',
            'caj_id_destino' => 'required|exists:cajas,caj_id',
            'monto' => 'required|numeric|min:1',
            'moneda' => 'required|in:GS,USD,BRL',
            'observacion' => 'nullable|string|max:200',
        ]);

        try {
            DB::transaction(function () use ($request, $cajas) {
                $usuario = $request->user();

                $origen = CajaSesion::with('caja')->whereKey($request->ses_id_origen)->lockForUpdate()->first();

                if ($origen->ses_estado !== 'ABIERTA') {
                    throw new NegocioException('La sesión de origen ya está cerrada.');
                }
                $this->exigirDuenoOPermiso($origen, $usuario, 'Solo podés transferir desde tu propia caja abierta.');

                if ((int) $origen->caj_id === (int) $request->caj_id_destino) {
                    throw new NegocioException('La caja de destino debe ser distinta de la de origen.');
                }

                $destino = CajaSesion::where('caj_id', $request->caj_id_destino)->where('ses_estado', 'ABIERTA')->lockForUpdate()->first();

                if (! $destino) {
                    throw new NegocioException('La caja de destino no tiene una sesión abierta. Pedí que la abran para recibir la transferencia.');
                }

                $nota = $request->filled('observacion') ? $request->observacion : 'Sin observación';
                $monto = (float) $request->monto;

                // Sale de la caja de origen (valida que haya efectivo suficiente) y entra en la de destino.
                $cajas->registrar(
                    $origen, 'EGRESO', $monto, $request->moneda,
                    'Transferencia a Caja ID '.$request->caj_id_destino.' | Transferencia de caja: '.$nota,
                    'EFECTIVO', ['caj_id_destino' => $request->caj_id_destino]
                );

                $cajas->registrar(
                    $destino, 'INGRESO', $monto, $request->moneda,
                    'Transferencia desde Caja ID '.$origen->caj_id.' | Transferencia de caja: '.$nota,
                    'EFECTIVO'
                );

                AuditoriaService::registrar('CAJA_TRANSFERENCIA', 'caja_sesiones', $origen->ses_id, [
                    'origen' => $origen->caj_id,
                    'destino' => (int) $request->caj_id_destino,
                    'monto' => $monto,
                    'moneda' => $request->moneda,
                ]);
            });
        } catch (NegocioException $e) {
            return back()->withErrors(['monto' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Transferencia realizada y registrada correctamente.');
    }

    public function cierres(Request $request)
    {
        $query = CajaSesion::with(['caja.sucursal', 'usuario'])
            ->where('ses_estado', 'CERRADA');

        // Filtro exacto por Fecha de Cierre
        if ($request->filled('fecha_cierre')) {
            $query->whereDate('ses_fecha_cierre', $request->fecha_cierre);
        }

        // Solo los cierres donde lo contado no coincide con lo esperado (en cualquier moneda)
        if ($request->boolean('solo_diferencias')) {
            $query->where(function ($q) {
                $q->where('ses_diferencia_gs', '<>', 0)
                  ->orWhere('ses_diferencia_usd', '<>', 0)
                  ->orWhere('ses_diferencia_brl', '<>', 0);
            });
        }

        // Paginación de 15 en 15 conservando el filtro en la URL
        $sesiones = $query->orderBy('ses_fecha_cierre', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('finanzas.cierres', compact('sesiones'));
    }

    public function cerrarCaja(Request $request, $ses_id, CajaService $cajas)
    {
        $request->validate([
            'cierre_gs' => 'required|numeric|min:0',
            'cierre_usd' => 'nullable|numeric|min:0',
            'cierre_brl' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:255',
        ]);

        try {
            $diferencias = DB::transaction(function () use ($request, $ses_id, $cajas) {
                $sesion = CajaSesion::whereKey($ses_id)->lockForUpdate()->firstOrFail();

                if ($sesion->ses_estado !== 'ABIERTA') {
                    throw new NegocioException('Esta caja ya fue cerrada.');
                }
                $this->exigirDuenoOPermiso($sesion, $request->user(), 'Solo quien abrió la caja (o un responsable) puede cerrarla.');

                // Lo que debería haber (según el libro) contra lo que se contó físicamente.
                $esperado = $cajas->esperadoDeSesion($sesion);
                $contado = [
                    'GS' => (float) $request->cierre_gs,
                    'USD' => (float) ($request->cierre_usd ?? 0),
                    'BRL' => (float) ($request->cierre_brl ?? 0),
                ];
                $dif = [];
                foreach ($contado as $moneda => $valor) {
                    $dif[$moneda] = round($valor - $esperado[$moneda], 2);
                }

                $sesion->update([
                    'ses_fecha_cierre' => now(),
                    'ses_monto_cierre_gs' => $contado['GS'],
                    'ses_monto_cierre_usd' => $contado['USD'],
                    'ses_monto_cierre_brl' => $contado['BRL'],
                    'ses_esperado_gs' => $esperado['GS'],
                    'ses_esperado_usd' => $esperado['USD'],
                    'ses_esperado_brl' => $esperado['BRL'],
                    'ses_diferencia_gs' => $dif['GS'],
                    'ses_diferencia_usd' => $dif['USD'],
                    'ses_diferencia_brl' => $dif['BRL'],
                    'ses_observacion_cierre' => $request->observacion,
                    'ses_estado' => 'CERRADA',
                ]);

                // Se retira el efectivo: la caja física queda en cero.
                $cajas->ponerSaldosEnCero($sesion->caj_id);

                AuditoriaService::registrar('CAJA_CIERRE', 'caja_sesiones', $sesion->ses_id, [
                    'esperado' => $esperado,
                    'contado' => $contado,
                    'diferencia' => $dif,
                ]);

                return $dif;
            });
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("Error al cerrar caja [{$codigo}]", ['sesion' => $ses_id, 'exception' => $e]);

            return back()->with('error', "No se pudo cerrar la caja. Avisá al administrador (código {$codigo}).");
        }

        $mensaje = '¡Caja cerrada correctamente!';
        $hayDiferencia = false;

        foreach ($diferencias as $moneda => $valor) {
            if (abs($valor) >= 0.01) {
                $hayDiferencia = true;
                $mensaje .= ' Diferencia en '.$moneda.': '.($valor > 0 ? 'sobrante ' : 'faltante ').number_format(abs($valor), 2, ',', '.').'.';
            }
        }

        return redirect()->route('finanzas.index')->with($hayDiferencia ? 'warning' : 'success', $mensaje);
    }

    /** Solo el dueño de la sesión, o quien tenga CAJA_OPERAR_AJENA, puede operar una caja abierta. */
    private function exigirDuenoOPermiso(CajaSesion $sesion, $usuario, string $mensaje): void
    {
        if ((int) $sesion->usu_id !== (int) $usuario->usu_id && ! $usuario->tienePermiso('CAJA_OPERAR_AJENA')) {
            throw new NegocioException($mensaje);
        }
    }
}
