<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaMovimiento;
use App\Models\Caja;
use App\Models\CajaSesion;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $query = CajaMovimiento::with(['sesion.caja.sucursal', 'sesion.usuario']);

        // 1. Filtro por Rango de Fechas (Desde / Hasta)
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('mov_fecha', [$request->fecha_inicio . ' 00:00:00', $request->fecha_fin . ' 23:59:59']);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate('mov_fecha', '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate('mov_fecha', '<=', $request->fecha_fin);
        }

        // 2. Buscador por Operación (Concepto) o Cliente / Caja
        if ($request->filled('buscar')) {
            $term = strtolower($request->buscar);
            $query->where(function($q) use ($term) {
                $q->whereRaw('LOWER(mov_concepto) LIKE ?', ["%{$term}%"])
                  ->orWhereHas('sesion.caja', function($c) use ($term) {
                      $c->whereRaw('LOWER(caj_nombre) LIKE ?', ["%{$term}%"]);
                  });
            });
        }

        // 3. Paginación para no saturar la hoja (15 registros por página)
        $movimientos = $query->orderBy('mov_id', 'desc')->paginate(15)->withQueryString();

        $cajas = Caja::with('sucursal')->where('caj_activa', true)->get();
        $sesionesAbiertas = CajaSesion::with('caja')->where('ses_estado', 'ABIERTA')->get();

        return view('finanzas.movimientos', compact('cajas', 'sesionesAbiertas', 'movimientos'));
    }
}