<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaSesion;
use App\Models\Sucursal;
use App\Models\IngresoEgreso;
use App\Models\CajaMovimiento;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IngresoEgresoController extends Controller
{
    public function index(Request $request)
    {
        $inicioSemana = Carbon::now()->startOfWeek()->format('Y-m-d');
        $finSemana = Carbon::now()->endOfWeek()->format('Y-m-d');

        // Sumatorias exclusivas de la nueva tabla
        $ingresosSemana = IngresoEgreso::where('ie_tipo', 'INGRESO')
                            ->whereBetween('ie_fecha', [$inicioSemana, $finSemana])
                            ->sum('ie_monto');

        $egresosSemana = IngresoEgreso::where('ie_tipo', 'EGRESO')
                            ->whereBetween('ie_fecha', [$inicioSemana, $finSemana])
                            ->sum('ie_monto');

        // Listados de la nueva tabla
        $queryIngresos = IngresoEgreso::with('sesion.caja.sucursal')->where('ie_tipo', 'INGRESO');
        $queryEgresos = IngresoEgreso::with('sesion.caja.sucursal')->where('ie_tipo', 'EGRESO');

        if ($request->filled('fecha')) {
            $queryIngresos->whereDate('ie_fecha', $request->fecha);
            $queryEgresos->whereDate('ie_fecha', $request->fecha);
        }
        if ($request->filled('caj_id')) {
            $queryIngresos->whereHas('sesion', function($q) use ($request) {
                $q->where('caj_id', $request->caj_id);
            });
            $queryEgresos->whereHas('sesion', function($q) use ($request) {
                $q->where('caj_id', $request->caj_id);
            });
        }
        if ($request->filled('suc_id')) {
            $queryIngresos->whereHas('sesion.caja', function($q) use ($request) {
                $q->where('suc_id', $request->suc_id);
            });
            $queryEgresos->whereHas('sesion.caja', function($q) use ($request) {
                $q->where('suc_id', $request->suc_id);
            });
        }

        $ingresos = $queryIngresos->orderBy('ie_id', 'desc')->get();
        $egresos = $queryEgresos->orderBy('ie_id', 'desc')->get();

        $sucursales = Sucursal::all();
        $cajasActivas = CajaSesion::with('caja')->where('ses_estado', 'ABIERTA')->get();

        return view('finanzas.ingresos_egresos', compact(
            'ingresos', 'egresos', 'ingresosSemana', 'egresosSemana', 'sucursales', 'cajasActivas'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:INGRESO,EGRESO',
            'ses_id' => 'required|exists:caja_sesiones,ses_id',
            'monto' => 'required|numeric|min:1',
            'concepto' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            
            // 1. Guardar en la nueva tabla (Para tu historial separado)
            IngresoEgreso::create([
                'ses_id' => $request->ses_id,
                'ie_tipo' => $request->tipo,
                'ie_monto' => $request->monto,
                'ie_concepto' => $request->concepto,
            ]);

            // 2. Registrar en el libro diario para que el arqueo de caja cuadre perfecto
            CajaMovimiento::create([
                'ses_id' => $request->ses_id,
                'mov_tipo' => $request->tipo,
                'mov_monto' => $request->monto,
                'mov_concepto' => 'MOV. EXTRA: ' . $request->concepto,
                'mov_moneda' => 'GS'
            ]);

            // 3. Afectar el saldo consolidado de la caja
            $caja = CajaSesion::find($request->ses_id)->caja;
            if ($request->tipo === 'INGRESO') {
                $caja->increment('caj_saldo_gs', $request->monto);
            } else {
                $caja->decrement('caj_saldo_gs', $request->monto);
            }
        });

        return back()->with('success', 'Operación registrada correctamente.');
    }
}