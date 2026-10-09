<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Exceptions\NegocioException;
use App\Services\VentaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public function anularVenta(Request $request, $id, VentaService $ventas)
    {
        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $venta = $ventas->anular((int) $id, $request->user(), $datos['motivo'] ?? null);
        } catch (NegocioException $e) {
            return redirect()->route('operaciones.ventas')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("Error anulando venta [{$codigo}]", ['venta' => $id, 'exception' => $e]);

            return redirect()->route('operaciones.ventas')
                ->with('error', "No se pudo anular la venta. Avisá al administrador (código {$codigo}).");
        }

        $mensaje = 'Venta Nro. '.$venta->vta_id.' anulada: stock devuelto y caja ajustada.';

        if ($venta->vta_nro_factura) {
            $mensaje .= ' Atención: tenía la factura Nro. '.$venta->vta_nro_factura.'. El sistema no emite nota de crédito; consultá con tu contador cómo registrarla.';
        }

        return redirect()->route('operaciones.ventas')->with('success', $mensaje);
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

    /**
     * Reimprime el ticket de una venta, marcado como COPIA. Queda registrado en la auditoría.
     * Las ventas anuladas no se reimprimen (no hay que entregar un comprobante de algo anulado).
     */
    public function reimprimir(Request $request, $id)
    {
        $venta = Venta::with(['detalles.producto', 'cliente', 'sesion.caja', 'sucursal'])->findOrFail($id);

        if ($venta->vta_estado === 'ANULADA') {
            return back()->with('error', 'La venta #'.$venta->vta_id.' está anulada: no se puede reimprimir su ticket.');
        }

        \App\Services\AuditoriaService::registrar('TICKET_REIMPRESO', 'ventas', $venta->vta_id, [
            'nro_factura' => $venta->vta_nro_factura,
        ]);

        $copia = true;

        return view($venta->vta_nro_factura ? 'pdv.ticket-factura' : 'pdv.ticket-simple', compact('venta', 'copia'));
    }

    public function procesarDevolucion(Request $request, $id, VentaService $ventas)
    {
        $datos = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Debe seleccionar al menos un ítem para devolver.',
        ]);

        try {
            $resultado = $ventas->devolver((int) $id, $datos['items'], $request->user(), $datos['motivo'] ?? null);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("Error procesando devolución [{$codigo}]", ['venta' => $id, 'exception' => $e]);

            return back()->with('error', "No se pudo procesar la devolución. Avisá al administrador (código {$codigo}).");
        }

        return back()->with('success', 'Devolución procesada por Gs. '.number_format($resultado['total'], 0, ',', '.').'. Stock, caja y deuda actualizados.');
    }
}
