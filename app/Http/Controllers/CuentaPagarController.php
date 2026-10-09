<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\CuentaPagar;
use App\Models\Proveedor;
use App\Services\PagoProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cuentas a pagar: lo que se le debe a cada proveedor, su antigüedad y los pagos.
 */
class CuentaPagarController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'prov_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:PENDIENTE,VENCIDAS,PAGADA,TODAS'],
        ]);
        $estado = $filtros['estado'] ?? 'PENDIENTE';
        $hoy = now()->toDateString();

        // Resumen de lo pendiente (siempre sobre todo lo pendiente, sin importar el filtro de la lista).
        $pendientes = CuentaPagar::with('proveedor:prov_id,prov_razonsocial')->where('cpa_estado', 'PENDIENTE')
            ->get(['cpa_id', 'prov_id', 'cpa_saldo_pendiente', 'cpa_fecha_vencimiento']);

        $antiguedad = ['vigente' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd60mas' => 0.0];
        $porProveedor = [];
        foreach ($pendientes as $c) {
            $saldo = (float) $c->cpa_saldo_pendiente;
            $venc = $c->cpa_fecha_vencimiento;
            $dias = $venc && $venc->toDateString() < $hoy ? $venc->diffInDays(now()->startOfDay()) : 0;

            if ($dias <= 0) {
                $antiguedad['vigente'] += $saldo;
            } elseif ($dias <= 30) {
                $antiguedad['d30'] += $saldo;
            } elseif ($dias <= 60) {
                $antiguedad['d60'] += $saldo;
            } else {
                $antiguedad['d60mas'] += $saldo;
            }

            $nombre = $c->proveedor->prov_razonsocial ?? ('#'.$c->prov_id);
            $porProveedor[$c->prov_id] ??= ['id' => $c->prov_id, 'nombre' => $nombre, 'deuda' => 0.0, 'vencida' => 0.0, 'cuentas' => 0];
            $porProveedor[$c->prov_id]['deuda'] += $saldo;
            $porProveedor[$c->prov_id]['cuentas']++;
            if ($dias > 0) {
                $porProveedor[$c->prov_id]['vencida'] += $saldo;
            }
        }
        usort($porProveedor, fn ($a, $b) => $b['deuda'] <=> $a['deuda']);

        $consulta = CuentaPagar::with(['proveedor', 'compra', 'pagos'])->orderBy('cpa_fecha_vencimiento')->orderBy('cpa_id');
        if (! empty($filtros['prov_id'])) {
            $consulta->where('prov_id', $filtros['prov_id']);
        }
        match ($estado) {
            'PENDIENTE' => $consulta->where('cpa_estado', 'PENDIENTE'),
            'VENCIDAS' => $consulta->where('cpa_estado', 'PENDIENTE')->whereDate('cpa_fecha_vencimiento', '<', $hoy),
            'PAGADA' => $consulta->where('cpa_estado', 'PAGADA'),
            default => $consulta->where('cpa_estado', '!=', 'ANULADA'),
        };

        return view('cuentas_pagar.index', [
            'cuentas' => $consulta->paginate(30)->withQueryString(),
            'proveedores' => Proveedor::orderBy('prov_razonsocial')->get(['prov_id', 'prov_razonsocial']),
            'antiguedad' => $antiguedad,
            'deudaTotal' => array_sum($antiguedad),
            'porProveedor' => $porProveedor,
            'estado' => $estado,
            'formas' => PagoProveedorService::FORMAS,
            'hoy' => $hoy,
        ]);
    }

    public function pagar(Request $request, $id, PagoProveedorService $pagos)
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', 'in:'.implode(',', PagoProveedorService::FORMAS)],
            'referencia' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $pagos->pagar((int) $id, (float) $datos['monto'], $datos['forma_pago'], $datos['referencia'] ?? null, $request->user());
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error al pagar a proveedor: ".$e->getMessage(), ['exception' => $e]);

            return back()->with('error', "No se pudo registrar el pago. Código de error: $codigo");
        }

        return back()->with('success', 'Pago registrado.');
    }

    public function anularPago(Request $request, $id, PagoProveedorService $pagos)
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:200']], ['motivo.required' => 'Indicá el motivo de la anulación.']);

        try {
            $pagos->anular((int) $id, $request->user(), $datos['motivo']);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error al anular pago a proveedor: ".$e->getMessage(), ['exception' => $e]);

            return back()->with('error', "No se pudo anular el pago. Código de error: $codigo");
        }

        return back()->with('success', 'Pago anulado: la deuda con el proveedor volvió a su valor anterior.');
    }
}
