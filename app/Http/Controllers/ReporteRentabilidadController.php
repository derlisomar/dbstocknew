<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReporteRentabilidadController extends Controller
{
    public function index(Request $request)
    {
        // 1. Aplicar Filtros Multivariables Combinados
        $query = DB::table('detalle_ventas')
            ->join('ventas', 'ventas.vta_id', '=', 'detalle_ventas.vta_id')
            ->join('productos', 'productos.pro_id', '=', 'detalle_ventas.pro_id')
            ->where('ventas.vta_estado', '!=', 'ANULADA');

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('ventas.vta_fecha', [$request->fecha_inicio . ' 00:00:00', $request->fecha_fin . ' 23:59:59']);
        }
        if ($request->filled('suc_id')) {
            $query->where('ventas.suc_id', $request->suc_id);
        }
        if ($request->filled('vta_formapago')) {
            $query->where('ventas.vta_formapago', $request->vta_formapago);
        }
        if ($request->filled('usu_id')) { // Cajero / Vendedor
            $query->where('ventas.usu_id', $request->usu_id);
        }

        // 2. Reporte de Rentabilidad y Agrupación para ABC
        $datos = $query->select(
            'productos.pro_id',
            'productos.pro_codigo',
            'productos.pro_nombre',
            'productos.pro_stockactual',
            DB::raw('SUM(detalle_ventas.det_cantidad) as total_vendido'),
            DB::raw('SUM(detalle_ventas.det_cantidad * detalle_ventas.det_preciounitario) as ingresos_totales'),
            DB::raw('SUM(detalle_ventas.det_cantidad * detalle_ventas.det_preciocosto) as costo_total')
        )
        ->groupBy('productos.pro_id', 'productos.pro_codigo', 'productos.pro_nombre', 'productos.pro_stockactual')
        ->orderByDesc('ingresos_totales')
        ->get();

        // 3. Cálculos de Curva ABC y Márgenes (%)
        $granTotalIngresos = $datos->sum('ingresos_totales');
        $acumulado = 0;

        foreach ($datos as $item) {
            // Utilidad Bruta y Margen %
            $item->utilidad_bruta = $item->ingresos_totales - $item->costo_total;
            $item->margen_porcentual = $item->ingresos_totales > 0 
                ? ($item->utilidad_bruta / $item->ingresos_totales) * 100 
                : 0;

            // Algoritmo Curva ABC (Ley de Pareto)
            $acumulado += $item->ingresos_totales;
            $porcentajeAcumulado = $granTotalIngresos > 0 ? ($acumulado / $granTotalIngresos) * 100 : 0;

            if ($porcentajeAcumulado <= 80) {
                $item->clasificacion_abc = 'A'; // 80% de ingresos (Cuidar stock)
            } elseif ($porcentajeAcumulado <= 95) {
                $item->clasificacion_abc = 'B'; // 15% de ingresos (Rotación moderada)
            } else {
                $item->clasificacion_abc = 'C'; // 5% de ingresos (Stock inmovilizado)
            }
        }

        // Listados para los desplegables de filtros
        $sucursales = Sucursal::all();
        $usuarios = User::all();

        return view('operaciones.reporte_abc', compact('datos', 'sucursales', 'usuarios', 'granTotalIngresos'));
    }

    // Funciones de Exportación Asíncrona simulada (Archivos limpios)
    public function exportarExcel(Request $request) { /* Lógica similar a la de ventas */ }
    public function exportarPdf(Request $request) { /* Lógica similar a la de ventas con DomPDF o vista de impresión */ }
}