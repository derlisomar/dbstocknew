<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CajaSesion;
use App\Models\Cotizacion;
use App\Models\CuentasCobrar;
use App\Models\DetalleDevolucion;
use App\Models\DetalleVenta;
use App\Models\Devolucion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Anulación y devolución de ventas.
 * Todo ocurre dentro de una transacción con las filas bloqueadas: si algo falla no queda nada a medias,
 * y dos personas no pueden anular o devolver lo mismo a la vez.
 */
class VentaService
{
    public function __construct(private CajaService $caja, private StockService $stock)
    {
    }

    // ------------------------------------------------------------------ ANULAR

    public function anular(int $ventaId, User $usuario, ?string $motivo = null): Venta
    {
        return DB::transaction(function () use ($ventaId, $usuario, $motivo) {
            $venta = Venta::whereKey($ventaId)->lockForUpdate()->first();

            if (! $venta) {
                throw new NegocioException('Venta no encontrada.');
            }
            if ($venta->vta_estado === 'ANULADA') {
                throw new NegocioException('Esta venta ya se encuentra anulada.');
            }

            // 1. Venta a crédito: se cancela la deuda, pero solo si el cliente no pagó nada todavía.
            if ($venta->vta_tipo === 'CREDITO') {
                $cuenta = CuentasCobrar::where('vta_id', $venta->vta_id)->lockForUpdate()->first();

                if ($cuenta) {
                    if (round((float) $cuenta->cred_saldo_pendiente, 2) < round((float) $cuenta->cred_monto_total, 2)) {
                        throw new NegocioException('No se puede anular: esta venta a crédito ya tiene cobros registrados. Resolvé primero los cobros con el responsable.');
                    }

                    $cuenta->update(['cred_saldo_pendiente' => 0, 'cred_estado' => 'ANULADA']);
                }
            }

            // 2. Stock: vuelve lo que todavía figura vendido (lo ya devuelto antes no se cuenta dos veces).
            $detalles = DetalleVenta::where('vta_id', $venta->vta_id)->get();
            Producto::whereIn('pro_id', $detalles->pluck('pro_id'))->lockForUpdate()->get();

            foreach ($detalles as $detalle) {
                if ((float) $detalle->det_cantidad > 0) {
                    $this->stock->mover(
                        (int) $detalle->pro_id, 'ANULACION_VENTA', (float) $detalle->det_cantidad,
                        $motivo ? 'Anulación: '.$motivo : 'Anulación de venta', 'venta:'.$venta->vta_id
                    );
                }
            }

            // 3. Dinero: solo las ventas de contado movieron caja.
            if ($venta->vta_tipo === 'CONTADO') {
                [$monto, $moneda, $forma] = $this->dineroPendienteDeReintegrar($venta);

                if ($monto > 0) {
                    $sesion = $this->sesionParaReintegro($venta, $usuario);

                    $this->caja->registrar(
                        $sesion, 'EGRESO', $monto, $moneda,
                        'Anulación Venta Nro. '.($venta->vta_nro_factura ?? $venta->vta_id).' ('.$forma.')',
                        $forma, ['vta_id' => $venta->vta_id], permitirSaldoNegativo: true
                    );
                }
            }

            // 4. Estado, quién y por qué.
            $venta->update([
                'vta_estado' => 'ANULADA',
                'vta_anulada_por' => $usuario->usu_id,
                'vta_anulada_fecha' => now(),
                'vta_motivo_anulacion' => $motivo ? mb_substr(trim($motivo), 0, 255) : 'Sin motivo indicado',
            ]);

            AuditoriaService::registrar('VENTA_ANULADA', 'ventas', $venta->vta_id, [
                'total' => (float) $venta->vta_total,
                'tipo' => $venta->vta_tipo,
                'factura' => $venta->vta_nro_factura,
                'motivo' => $venta->vta_motivo_anulacion,
            ]);

            return $venta;
        });
    }

    // ------------------------------------------------------------------ DEVOLVER

    /**
     * @param  array<int|string, mixed>  $items  [det_vta_id => cantidad a devolver]
     * @return array{total: float, devolucion: Devolucion}
     */
    public function devolver(int $ventaId, array $items, User $usuario, ?string $motivo = null): array
    {
        return DB::transaction(function () use ($ventaId, $items, $usuario, $motivo) {
            $venta = Venta::whereKey($ventaId)->lockForUpdate()->first();

            if (! $venta) {
                throw new NegocioException('Venta no encontrada.');
            }
            if ($venta->vta_estado === 'ANULADA') {
                throw new NegocioException('No se pueden procesar devoluciones de una venta ya anulada.');
            }

            // Solo cantidades numéricas mayores a cero.
            $pedidos = collect($items)
                ->filter(fn ($c) => is_numeric($c) && (float) $c > 0)
                ->map(fn ($c) => (float) $c);

            if ($pedidos->isEmpty()) {
                throw new NegocioException('Debe seleccionar al menos un ítem para devolver.');
            }

            // Los ítems tienen que ser de ESTA venta (antes se aceptaba cualquier id de cualquier venta).
            $detalles = DetalleVenta::where('vta_id', $venta->vta_id)
                ->whereIn('det_vta_id', $pedidos->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('det_vta_id');

            if ($detalles->count() !== $pedidos->count()) {
                throw new NegocioException('Alguno de los ítems no pertenece a esta venta.');
            }

            $productos = Producto::whereIn('pro_id', $detalles->pluck('pro_id'))->lockForUpdate()->get()->keyBy('pro_id');

            $totalGs = 0.0;
            $ivaExenta = 0.0;
            $iva5 = 0.0;
            $iva10 = 0.0;
            $lineas = [];

            foreach ($pedidos as $detId => $cantidad) {
                /** @var DetalleVenta $detalle */
                $detalle = $detalles->get($detId);

                if ($cantidad > (float) $detalle->det_cantidad + 0.0001) {
                    throw new NegocioException('La cantidad a devolver excede lo vendido originalmente.');
                }

                $producto = $productos->get($detalle->pro_id);
                $subtotal = round((float) $detalle->det_preciounitario * $cantidad, 2);
                $totalGs += $subtotal;

                // Mismo reparto de IVA que al vender (IVA incluido en el precio).
                $tipoIva = (int) ($producto->pro_tipo_iva ?? 0);
                if ($tipoIva === 10) {
                    $iva10 += $subtotal / 11;
                } elseif ($tipoIva === 5) {
                    $iva5 += $subtotal / 21;
                } else {
                    $ivaExenta += $subtotal;
                }

                $this->stock->mover(
                    (int) $detalle->pro_id, 'DEVOLUCION', $cantidad,
                    $motivo ? 'Devolución: '.$motivo : 'Devolución de venta', 'venta:'.$venta->vta_id
                );

                $restante = round((float) $detalle->det_cantidad - $cantidad, 2);
                $detalle->update([
                    'det_cantidad' => $restante,
                    'det_subtotal' => round($restante * (float) $detalle->det_preciounitario, 2),
                ]);

                $lineas[] = [
                    'det_vta_id' => $detalle->det_vta_id,
                    'pro_id' => $detalle->pro_id,
                    'ddv_cantidad' => $cantidad,
                    'ddv_preciounitario' => $detalle->det_preciounitario,
                    'ddv_subtotal' => $subtotal,
                ];
            }

            $totalGs = round($totalGs, 2);

            // Lo que ya entró por esta venta, ANTES de tocar el total (para convertir a otra moneda con la tasa original).
            $totalAntes = (float) $venta->vta_total;
            $neto = $this->caja->netoDeVenta($venta->vta_id);

            $venta->update([
                'vta_total' => max(round($totalAntes - $totalGs, 2), 0),
                'vta_total_exenta' => max(round((float) $venta->vta_total_exenta - $ivaExenta, 2), 0),
                'vta_total_iva5' => max(round((float) $venta->vta_total_iva5 - $iva5, 2), 0),
                'vta_total_iva10' => max(round((float) $venta->vta_total_iva10 - $iva10, 2), 0),
            ]);

            // Dinero a reintegrar (en guaraníes): contado = todo; crédito = solo lo que excede la deuda.
            $reintegroGs = $totalGs;

            if ($venta->vta_tipo === 'CREDITO') {
                $cuenta = CuentasCobrar::where('vta_id', $venta->vta_id)->lockForUpdate()->first();
                $reintegroGs = $totalGs;

                if ($cuenta) {
                    $reduce = min($totalGs, (float) $cuenta->cred_saldo_pendiente);
                    $saldo = round((float) $cuenta->cred_saldo_pendiente - $reduce, 2);

                    $cuenta->update([
                        'cred_monto_total' => max(round((float) $cuenta->cred_monto_total - $reduce, 2), 0),
                        'cred_saldo_pendiente' => $saldo,
                        'cred_estado' => $saldo <= 0 ? 'PAGADA' : $cuenta->cred_estado,
                    ]);

                    $reintegroGs = round($totalGs - $reduce, 2);
                }
            }

            $sesionId = $venta->ses_id;

            if ($venta->vta_tipo === 'CONTADO' || $reintegroGs > 0) {
                $sesion = $this->sesionParaReintegro($venta, $usuario);
                $sesionId = $sesion->ses_id;

                [$moneda, $forma] = $neto ? [$neto['moneda'], $neto['forma']] : [strtoupper($venta->vta_moneda ?: 'GS'), 'EFECTIVO'];
                $monto = $this->aMonedaDeCaja($reintegroGs, $moneda, $totalAntes, $neto);

                if ($monto > 0) {
                    $this->caja->registrar(
                        $sesion, 'EGRESO', $monto, $moneda,
                        'Devolución de ítems - Venta Nro. '.($venta->vta_nro_factura ?? $venta->vta_id).' ('.$forma.')',
                        $forma, ['vta_id' => $venta->vta_id], permitirSaldoNegativo: true
                    );
                }
            }

            $devolucion = Devolucion::create([
                'vta_id' => $venta->vta_id,
                'usu_id' => $usuario->usu_id,
                'ses_id' => $sesionId,
                'dev_fecha' => now(),
                'dev_total' => $totalGs,
                'dev_motivo' => $motivo ? mb_substr(trim($motivo), 0, 255) : null,
            ]);

            foreach ($lineas as $linea) {
                DetalleDevolucion::create($linea + ['dev_id' => $devolucion->dev_id]);
            }

            AuditoriaService::registrar('VENTA_DEVOLUCION', 'ventas', $venta->vta_id, [
                'devolucion' => $devolucion->dev_id,
                'total' => $totalGs,
                'items' => count($lineas),
                'factura' => $venta->vta_nro_factura,
            ]);

            return ['total' => $totalGs, 'devolucion' => $devolucion];
        });
    }

    // ------------------------------------------------------------------ ayudas

    /**
     * Cuánto falta reintegrar de una venta de contado, en qué moneda y con qué forma de pago.
     * Con movimientos vinculados sale exacto del libro; las ventas viejas se estiman con su total.
     *
     * @return array{0: float, 1: string, 2: string}
     */
    private function dineroPendienteDeReintegrar(Venta $venta): array
    {
        $neto = $this->caja->netoDeVenta($venta->vta_id);

        if ($neto) {
            return [$neto['monto'], $neto['moneda'], $neto['forma']];
        }

        $moneda = strtoupper($venta->vta_moneda ?: 'GS');
        $monto = (float) $venta->vta_total;

        if ($moneda !== 'GS') {
            $cot = Cotizacion::where('cot_activa', true)->first();
            $tasa = $moneda === 'USD' ? ($cot->cot_dolar ?? 7500) : ($cot->cot_real ?? 1500);
            $monto = round($monto / $tasa, 2);
        }

        return [round($monto, 2), $moneda, 'EFECTIVO'];
    }

    /** Pasa un monto en guaraníes a la moneda en que se cobró la venta. */
    private function aMonedaDeCaja(float $montoGs, string $moneda, float $totalAntesGs, ?array $neto): float
    {
        if ($moneda === 'GS') {
            return round($montoGs, 2);
        }

        // Con el libro: misma proporción que lo cobrado (conserva la tasa original de esa venta).
        if ($neto && $totalAntesGs > 0) {
            return round($montoGs / $totalAntesGs * $neto['monto'], 2);
        }

        $cot = Cotizacion::where('cot_activa', true)->first();
        $tasa = $moneda === 'USD' ? ($cot->cot_dolar ?? 7500) : ($cot->cot_real ?? 1500);

        return round($montoGs / $tasa, 2);
    }

    /**
     * Dónde se anota la salida de dinero: en la sesión original si sigue abierta;
     * si ya se cerró, en la caja abierta de quien hace la operación (de ahí sale el efectivo).
     */
    private function sesionParaReintegro(Venta $venta, User $usuario): CajaSesion
    {
        $original = $venta->ses_id ? CajaSesion::whereKey($venta->ses_id)->lockForUpdate()->first() : null;

        if ($original && $original->ses_estado === 'ABIERTA') {
            return $original;
        }

        $propia = $this->caja->sesionAbiertaDe($usuario);

        if (! $propia) {
            throw new NegocioException('La caja donde se hizo la venta ya está cerrada. Abrí tu caja para registrar la salida de dinero y volvé a intentar.');
        }

        return $propia;
    }
}
