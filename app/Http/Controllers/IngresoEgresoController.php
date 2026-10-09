<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaSesion;
use App\Models\Sucursal;
use App\Models\IngresoEgreso;
use App\Exceptions\NegocioException;
use App\Services\AuditoriaService;
use App\Services\CajaService;
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

    public function store(Request $request, CajaService $cajas)
    {
        $request->validate([
            'tipo' => 'required|in:INGRESO,EGRESO',
            'ses_id' => 'required|exists:caja_sesiones,ses_id',
            'monto' => 'required|numeric|min:1',
            'concepto' => 'required|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request, $cajas) {
                $sesion = CajaSesion::whereKey($request->ses_id)->lockForUpdate()->first();

                // Solo en una caja ABIERTA, y solo la propia (o con permiso de responsable).
                if ($sesion->ses_estado !== 'ABIERTA') {
                    throw new NegocioException('Esa caja ya está cerrada: no se pueden registrar movimientos.');
                }
                if ((int) $sesion->usu_id !== (int) $request->user()->usu_id && ! $request->user()->tienePermiso('CAJA_OPERAR_AJENA')) {
                    throw new NegocioException('Solo podés registrar movimientos en tu propia caja abierta.');
                }

                // 1. Historial separado de ingresos y egresos
                IngresoEgreso::create([
                    'ses_id' => $sesion->ses_id,
                    'ie_tipo' => $request->tipo,
                    'ie_monto' => $request->monto,
                    'ie_concepto' => $request->concepto,
                ]);

                // 2. Libro de caja + saldo físico (un egreso no puede dejar la caja en negativo)
                $cajas->registrar($sesion, $request->tipo, (float) $request->monto, 'GS', 'MOV. EXTRA: '.$request->concepto, 'EFECTIVO');

                AuditoriaService::registrar('CAJA_'.$request->tipo.'_EXTRA', 'caja_sesiones', $sesion->ses_id, [
                    'monto' => (float) $request->monto,
                    'concepto' => $request->concepto,
                ]);
            });
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Operación registrada correctamente.');
    }
}
