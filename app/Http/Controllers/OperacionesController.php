<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Cotizacion;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class OperacionesController extends Controller
{
public function historialVentas(Request $request)
    {
        $query = Venta::with(['cliente', 'usuario', 'detalles.producto']);

        // Filtro por Cliente específico
        if ($request->filled('cli_id')) {
            $query->where('cli_id', $request->cli_id);
        }

        // Filtro por Rango de Fechas (Si no se envían, asigna por defecto el mes actual)
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->endOfMonth()->format('Y-m-d'));

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('vta_fecha', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);
        } elseif ($fechaInicio) {
            $query->whereDate('vta_fecha', '>=', $fechaInicio);
        } elseif ($fechaFin) {
            $query->whereDate('vta_fecha', '<=', $fechaFin);
        }

        // Filtro por Tipo de Venta (Contado / Crédito)
        if ($request->filled('vta_tipo')) {
            $query->where('vta_tipo', $request->vta_tipo);
        }

        // Filtro por Forma de Pago
        if ($request->filled('vta_formapago')) {
            $query->where('vta_formapago', $request->vta_formapago);
        }

        // 1. CLONAR LA CONSULTA PARA CALCULAR ESTADÍSTICAS GLOBALES (SIN PAGINAR)
        $queryStats = clone $query;
        $queryStats->where('vta_estado', '!=', 'ANULADA');
        $totalComprasCount = $queryStats->count();
        $totalMontoGs = $queryStats->sum('vta_total');

        // 2. PAGINACIÓN: Traer las ventas de 20 en 20 conservando los filtros en la URL
        $ventas = $query->orderBy('vta_id', 'desc')->paginate(20)->withQueryString();

        // Listado de clientes para el selector de filtro
        $clientes = Cliente::orderBy('cli_nombre')->get();

        // Tasas de cambio actuales
        $cotizacion = Cotizacion::where('cot_activa', true)->first();
        $tasaUsd = $cotizacion ? $cotizacion->cot_dolar : 7500;
        $tasaBrl = $cotizacion ? $cotizacion->cot_real : 1500;

        return view('operaciones.ventas', compact(
            'ventas', 
            'clientes', 
            'tasaUsd', 
            'tasaBrl', 
            'totalComprasCount', 
            'totalMontoGs'
        ));
    }
    public function anularVenta(Request $request, $id)
    {
        try {
            $venta = Venta::with(['detalles.producto', 'sesion.caja'])->find($id);

            if (!$venta) {
                return redirect()->route('operaciones.ventas')->with('error', 'Venta no encontrada.');
            }

            if ($venta->vta_estado === 'ANULADA') {
                return redirect()->route('operaciones.ventas')->with('warning', 'Esta venta ya se encuentra anulada.');
            }

            $usuario = \Auth::user();

            // 1. Validar Permisos (Opcional: Si solo Admin puede anular)
            // if ($usuario->role !== 'admin') { ... return error ... }

            DB::beginTransaction();

            // 2. Devolver el Stock al Inventario
            foreach ($venta->detalles as $detalle) {
                $producto = $detalle->producto;
                $producto->pro_stockactual += $detalle->det_cantidad;
                $producto->save();
            }

            // 3. Registrar el Movimiento de Egreso en Caja (Reversión de dinero)
            // Buscamos la caja asociada a la sesión donde se realizó la venta
            $sesion = $venta->sesion;
            $caja = $sesion ? $sesion->caja : null;

            if ($caja) {
                $monto = $venta->vta_total;
                $columnaSaldo = 'caj_saldo_' . strtolower($venta->vta_moneda ?? 'gs');
                
                // Restar el monto de la caja
                $caja->decrement($columnaSaldo, $monto);

                // Registrar el egreso
                \App\Models\CajaMovimiento::create([
                    'ses_id' => $sesion->ses_id,
                    'mov_tipo' => 'EGRESO', // OJO: Egreso
                    'mov_monto' => $monto,
                    'mov_concepto' => 'Anulación Venta Nro. ' . $venta->vta_id,
                    'mov_moneda' => $venta->vta_moneda ?? 'GS',
                ]);
            } else {
                 \Log::warning("No se pudo anular movimiento de caja para Venta ID: {$venta->vta_id}");
            }

            // 4. Actualizar el Estado de la Venta a ANULADA
            $venta->vta_estado = 'ANULADA';
            $venta->save();

            DB::commit();

            return redirect()->route('operaciones.ventas')->with('success', 'Venta Nro. ' . $venta->vta_id . ' anulada correctamente y stock devuelto.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error anulando venta: " . $e->getMessage());
            return redirect()->route('operaciones.ventas')->with('error', 'Error al anular la venta: ' . $e->getMessage());
        }
    }

    public function exportarExcel(Request $request)
    {
        $query = Venta::with(['cliente', 'usuario', 'detalles.producto']);

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('vta_fecha', [$request->fecha_inicio . ' 00:00:00', $request->fecha_fin . ' 23:59:59']);
        }
        if ($request->filled('vta_tipo')) {
            $query->where('vta_tipo', $request->vta_tipo);
        }
        if ($request->filled('vta_formapago')) {
            $query->where('vta_formapago', $request->vta_formapago);
        }

        $ventas = $query->orderBy('vta_id', 'desc')->get();

        $filename = "historial_ventas_" . date('Y-m-d_H-i-s') . ".csv";
        
        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use ($ventas) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel reconozca correctamente las tildes y caracteres latinos
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados de columnas
            fputcsv($file, ['ID Venta', 'Fecha', 'Cliente', 'Tipo', 'Forma Pago', 'Total (Gs.)', 'Estado'], ';');

            foreach ($ventas as $v) {
                fputcsv($file, [
                    $v->vta_id,
                    $v->vta_fecha,
                    $v->cliente->cli_nombre ?? 'Consumidor Final',
                    $v->vta_tipo,
                    $v->vta_formapago ?? 'N/A',
                    $v->vta_total,
                    $v->vta_estado
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportarPdf(Request $request)
    {
        $query = Venta::with(['cliente', 'usuario', 'detalles.producto']);

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('vta_fecha', [$request->fecha_inicio . ' 00:00:00', $request->fecha_fin . ' 23:59:59']);
        }
        if ($request->filled('vta_tipo')) {
            $query->where('vta_tipo', $request->vta_tipo);
        }
        if ($request->filled('vta_formapago')) {
            $query->where('vta_formapago', $request->vta_formapago);
        }

        $ventas = $query->orderBy('vta_id', 'desc')->get();
        
        $cotizacion = Cotizacion::where('cot_activa', true)->first();
        $tasaUsd = $cotizacion ? $cotizacion->cot_dolar : 7500;
        $tasaBrl = $cotizacion ? $cotizacion->cot_real : 1500;

        return view('operaciones.pdf', compact('ventas', 'tasaUsd', 'tasaBrl'));
    }

    public function procesarDevolucion(Request $request, $id)
    {
    try {
        \Illuminate\Support\Facades\DB::beginTransaction();

        $venta = \App\Models\Venta::with('detalles')->findOrFail($id);

        if ($venta->vta_estado === 'ANULADA') {
            return back()->with('error', 'No se pueden procesar devoluciones de una venta ya anulada.');
        }

        $itemsDevueltos = $request->input('items', []); // Array [det_vta_id => cantidad]
        if (empty($itemsDevueltos)) {
            return back()->with('error', 'Debe seleccionar al menos un ítem para devolver.');
        }

        $montoTotalDevolucion = 0;

        foreach ($itemsDevueltos as $detVtaId => $cantidadDevolver) {
            $cantidadDevolver = intval($cantidadDevolver);
            if ($cantidadDevolver <= 0) continue;

            $detalle = \App\Models\DetalleVenta::findOrFail($detVtaId);

            if ($cantidadDevolver > $detalle->det_cantidad) {
                return back()->with('error', 'La cantidad a devolver excede lo vendido originalmente.');
            }

            // 1. Restaurar stock del producto
            $producto = \App\Models\Producto::find($detalle->pro_id);
            if ($producto) {
                $producto->increment('pro_stockactual', $cantidadDevolver);
            }

            // 2. Calcular monto proporcional devuelto
            $subtotalDevuelto = $detalle->det_preciounitario * $cantidadDevolver;
            $montoTotalDevolucion += $subtotalDevuelto;

            // 3. Actualizar detalle de venta
            $detalle->det_cantidad -= $cantidadDevolver;
            $detalle->det_subtotal = $detalle->det_cantidad * $detalle->det_preciounitario;
            $detalle->save();
        }

        // Actualizar el total general de la venta
        $venta->vta_total -= $montoTotalDevolucion;
        if ($venta->vta_total < 0) $venta->vta_total = 0;
        $venta->save();

        // 4. Afectar la Caja (Registrar egreso si fue al CONTADO)
        if ($venta->vta_tipo === 'CONTADO' && $montoTotalDevolucion > 0) {
            \App\Models\CajaMovimiento::create([
                'ses_id' => $venta->ses_id,
                'mov_tipo' => 'EGRESO',
                'mov_monto' => $montoTotalDevolucion,
                'mov_concepto' => 'Devolución de ítems - Venta Nro. ' . $venta->vta_id,
                'mov_moneda' => 'GS'
            ]);

            $sesion = \App\Models\CajaSesion::with('caja')->find($venta->ses_id);
            if ($sesion && $sesion->caja) {
                $sesion->caja->decrement('caj_saldo_gs', $montoTotalDevolucion);
            }
        }

        \Illuminate\Support\Facades\DB::commit();
        return back()->with('success', 'Devolución procesada correctamente. Stock y caja actualizados.');

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return back()->with('error', 'Error al procesar la devolución: ' . $e->getMessage());
    }
}

}