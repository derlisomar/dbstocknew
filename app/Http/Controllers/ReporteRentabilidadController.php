<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteRentabilidadController extends Controller
{
    public function index(Request $request)
    {
        [$datos, $granTotalIngresos] = $this->calcular($request);

        $sucursales = Sucursal::all();

        return view('operaciones.reporte_abc', compact('datos', 'sucursales', 'granTotalIngresos'));
    }

    /** Descarga CSV (abre directo en Excel: separador ; y acentos correctos). */
    public function exportarExcel(Request $request)
    {
        [$datos] = $this->calcular($request);

        return response()->streamDownload(function () use ($datos) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Clase', 'Codigo', 'Producto', 'Unidades vendidas', 'Ingreso', 'Costo', 'Utilidad bruta', 'Margen %', 'Stock actual', 'Observacion'], ';');
            foreach ($datos as $d) {
                fputcsv($out, [
                    $d->clasificacion_abc, $d->pro_codigo, $d->pro_nombre,
                    (float) $d->total_vendido, round($d->ingresos_totales), round($d->costo_total), round($d->utilidad_bruta),
                    $d->sin_costo ? '' : number_format($d->margen_porcentual, 1, ',', ''),
                    (float) $d->pro_stockactual,
                    $d->sin_costo ? 'SIN COSTO CARGADO' : '',
                ], ';');
            }
            fclose($out);
        }, 'rentabilidad_abc_'.date('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Vista lista para imprimir o guardar como PDF desde el navegador. */
    public function exportarPdf(Request $request)
    {
        [$datos, $granTotalIngresos] = $this->calcular($request);

        return view('operaciones.reporte_abc_pdf', compact('datos', 'granTotalIngresos'));
    }

    /**
     * @return array{0: Collection, 1: float}
     */
    private function calcular(Request $request): array
    {
        $filtros = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'suc_id' => ['nullable', 'integer'],
            'vta_formapago' => ['nullable', 'string', 'max:30'],
            'usu_id' => ['nullable', 'integer'],
        ]);

        $query = DB::table('detalle_ventas')
            ->join('ventas', 'ventas.vta_id', '=', 'detalle_ventas.vta_id')
            ->join('productos', 'productos.pro_id', '=', 'detalle_ventas.pro_id')
            ->where('ventas.vta_estado', '!=', 'ANULADA');

        if (! empty($filtros['fecha_inicio'])) {
            $query->where('ventas.vta_fecha', '>=', $filtros['fecha_inicio'].' 00:00:00');
        }
        if (! empty($filtros['fecha_fin'])) {
            $query->where('ventas.vta_fecha', '<=', $filtros['fecha_fin'].' 23:59:59');
        }
        if (! empty($filtros['suc_id'])) {
            $query->where('ventas.suc_id', $filtros['suc_id']);
        }
        if (! empty($filtros['vta_formapago'])) {
            $query->where('ventas.vta_formapago', $filtros['vta_formapago']);
        }
        if (! empty($filtros['usu_id'])) {
            $query->where('ventas.usu_id', $filtros['usu_id']);
        }

        $datos = $query->select(
            'productos.pro_id',
            'productos.pro_codigo',
            'productos.pro_nombre',
            'productos.pro_stockactual',
            DB::raw('SUM(detalle_ventas.det_cantidad) as total_vendido'),
            DB::raw('SUM(detalle_ventas.det_cantidad * detalle_ventas.det_preciounitario) as ingresos_totales'),
            DB::raw('SUM(detalle_ventas.det_cantidad * COALESCE(detalle_ventas.det_preciocosto, 0)) as costo_total')
        )
            ->groupBy('productos.pro_id', 'productos.pro_codigo', 'productos.pro_nombre', 'productos.pro_stockactual')
            ->havingRaw('SUM(detalle_ventas.det_cantidad) > 0')
            ->orderByDesc('ingresos_totales')
            ->get();

        $granTotal = (float) $datos->sum('ingresos_totales');
        $acumuladoAntes = 0.0;

        foreach ($datos as $item) {
            $item->ingresos_totales = (float) $item->ingresos_totales;
            $item->costo_total = (float) $item->costo_total;
            $item->utilidad_bruta = $item->ingresos_totales - $item->costo_total;

            // Sin costo cargado el margen sería 100% y engaña: se marca aparte.
            $item->sin_costo = $item->costo_total <= 0;
            $item->margen_porcentual = ($item->ingresos_totales > 0 && ! $item->sin_costo)
                ? ($item->utilidad_bruta / $item->ingresos_totales) * 100
                : 0;

            // Curva ABC: la clase depende de lo acumulado ANTES de sumar este producto.
            // Así un producto que por sí solo pasa el 80% de las ventas igual es clase A.
            $porcentajeAntes = $granTotal > 0 ? ($acumuladoAntes / $granTotal) * 100 : 0;
            $item->clasificacion_abc = $porcentajeAntes < 80 ? 'A' : ($porcentajeAntes < 95 ? 'B' : 'C');

            $acumuladoAntes += $item->ingresos_totales;
        }

        return [$datos, $granTotal];
    }
}
