<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\CajaMovimiento;
use Illuminate\Support\Facades\DB;

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

    // Guardar la apertura de caja (Crear sesión abierta)
    public function aperturaStore(Request $request)
    {
        $request->validate([
            'caj_id' => 'required|exists:cajas,caj_id',
            'ses_monto_inicial_gs' => 'required|numeric|min:0',
        ]);

        $sesionActiva = CajaSesion::where('caj_id', $request->caj_id)
                                  ->where('ses_estado', 'ABIERTA')
                                  ->first();

        if ($sesionActiva) {
            return back()->withErrors(['caj_id' => 'Esta caja ya tiene una sesión abierta actualmente.']);
        }

        $usuario = auth()->user();

        CajaSesion::create([
            'caj_id' => $request->caj_id,
            'usu_id' => $usuario->usu_id ?? 1,
            'ses_fecha_apertura' => now(),
            'ses_monto_inicial_gs' => $request->ses_monto_inicial_gs,
            'ses_monto_inicial_usd' => $request->ses_monto_inicial_usd ?? 0,
            'ses_monto_inicial_brl' => $request->ses_monto_inicial_brl ?? 0,
            'ses_estado' => 'ABIERTA'
        ]);

        return redirect()->route('finanzas.index')->with('success', '¡Caja aperturada con éxito!');
    }

    public function movimientos()
    {
        $cajas = Caja::with('sucursal')->where('caj_activa', true)->get();
        $movimientos = CajaMovimiento::with('sesion.caja')->orderBy('mov_id', 'desc')->take(50)->get();
        return view('finanzas.movimientos', compact('cajas', 'movimientos'));
    }

    public function transferir(Request $request)
{
    $request->validate([
        'ses_id_origen' => 'required|exists:caja_sesiones,ses_id',
        'caj_id_destino' => 'required|exists:cajas,caj_id',
        'monto' => 'required|numeric|min:1',
        'moneda' => 'required|in:GS,USD,BRL',
        'observacion' => 'nullable|string'
    ]);

    $sesionOrigen = \App\Models\CajaSesion::with('caja')->find($request->ses_id_origen);
    $cajaOrigen = $sesionOrigen->caja;
    $campoSaldo = 'caj_saldo_' . strtolower($request->moneda);

    // Validación estricta de saldo suficiente
    if ($cajaOrigen->$campoSaldo < $request->monto) {
        return back()->withErrors(['monto' => '❌ Error: La caja de origen NO tiene saldo suficiente (' . $request->moneda . ') para transferir ese monto.']);
    }

    // Construir el concepto con el prefijo obligatorio pedido
    $conceptoBase = 'Transferencia a Caja ID ' . $request->caj_id_destino;
    if ($request->filled('observacion')) {
        $conceptoBase .= ' | Transferencia de caja: ' . $request->observacion;
    } else {
        $conceptoBase .= ' | Transferencia de caja: Sin observación';
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($request, $cajaOrigen, $campoSaldo, $conceptoBase) {
        // 1. Registrar EGRESO en origen
        \App\Models\CajaMovimiento::create([
            'ses_id' => $request->ses_id_origen,
            'mov_tipo' => 'EGRESO',
            'mov_monto' => $request->monto,
            'mov_concepto' => $conceptoBase,
            'mov_moneda' => $request->moneda,
            'caj_id_destino' => $request->caj_id_destino
        ]);

        // 2. Registrar INGRESO si la caja destino tiene sesión abierta
        $sesionDestino = \App\Models\CajaSesion::where('caj_id', $request->caj_id_destino)
                                   ->where('ses_estado', 'ABIERTA')->first();
        
        if ($sesionDestino) {
            \App\Models\CajaMovimiento::create([
                'ses_id' => $sesionDestino->ses_id,
                'mov_tipo' => 'INGRESO',
                'mov_monto' => $request->monto,
                'mov_concepto' => $conceptoBase,
                'mov_moneda' => $request->moneda
            ]);
        }

        // 3. Descontar e Incrementar Saldos Reales
        $cajaDestino = \App\Models\Caja::find($request->caj_id_destino);
        
        $cajaOrigen->decrement($campoSaldo, $request->monto);
        $cajaDestino->increment($campoSaldo, $request->monto);
    });

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

                // Paginación de 15 en 15 conservando el filtro en la URL
                $sesiones = $query->orderBy('ses_fecha_cierre', 'desc')
                                ->paginate(15)
                                ->withQueryString();

                return view('finanzas.cierres', compact('sesiones'));
            }
    public function cerrarCaja(Request $request, $ses_id)
    {
        $request->validate([
            'cierre_gs' => 'required|numeric|min:0',
        ]);

        $sesion = CajaSesion::with('caja')->findOrFail($ses_id);

        DB::transaction(function () use ($request, $sesion) {
            // 1. Actualizar la sesión a CERRADA y guardar los montos del arqueo físico
            $sesion->update([
                'ses_fecha_cierre' => now(),
                'ses_monto_cierre_gs' => $request->cierre_gs,
                'ses_monto_cierre_usd' => $request->cierre_usd ?? 0,
                'ses_monto_cierre_brl' => $request->cierre_brl ?? 0,
                'ses_estado' => 'CERRADA'
            ]);

            // 2. Poner a cero los saldos consolidados de la caja física al retirar el efectivo
            $caja = $sesion->caja;
            if ($caja) {
                $caja->update([
                    'caj_saldo_gs' => 0,
                    'caj_saldo_usd' => 0,
                    'caj_saldo_brl' => 0
                ]);
            }
        });

        return redirect()->route('finanzas.index')->with('success', '¡Caja cerrada correctamente y saldos actualizados a cero!');
    }
}