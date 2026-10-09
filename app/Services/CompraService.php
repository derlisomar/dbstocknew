<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Compra;
use App\Models\CuentaPagar;
use App\Models\DetalleCompra;
use App\Models\PagoProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compras a proveedores: entra la mercadería (con historial de stock), se actualiza el costo real
 * del producto y se genera la cuenta a pagar. Anular y devolver deshacen todo en la misma transacción.
 */
class CompraService
{
    public function __construct(
        private StockService $stock,
        private PagoProveedorService $pagos,
    ) {
    }

    /**
     * @param  array{prov_id:int, fecha?:?string, nro_documento?:?string, tipo:string, vencimiento?:?string,
     *               forma_pago?:?string, referencia?:?string, observacion?:?string, caj_id?:?int,
     *               items:array<int,array{pro_id:int,cantidad:float|string,costo:float|string}>}  $d
     * @return array{compra: Compra, avisos: list<string>}
     */
    public function registrar(array $d, User $usuario): array
    {
        $tipo = strtoupper((string) ($d['tipo'] ?? ''));
        if (! in_array($tipo, ['CONTADO', 'CREDITO'], true)) {
            throw new NegocioException('Elegí si la compra es al contado o a crédito.');
        }

        $lineas = $this->juntarLineas($d['items'] ?? []);
        if ($lineas === []) {
            throw new NegocioException('Agregá al menos un producto a la compra.');
        }

        if (! Proveedor::whereKey($d['prov_id'] ?? 0)->exists()) {
            throw new NegocioException('Proveedor no encontrado.');
        }

        $fecha = ! empty($d['fecha']) ? Carbon::parse($d['fecha'])->startOfDay() : now()->startOfDay();
        $hoy = now()->endOfDay();
        if ($fecha->gt($hoy)) {
            throw new NegocioException('La fecha de la compra no puede ser futura.');
        }

        $nroDoc = isset($d['nro_documento']) ? trim((string) $d['nro_documento']) : '';
        $nroDoc = $nroDoc === '' ? null : mb_substr($nroDoc, 0, 40);

        $vencimiento = null;
        if ($tipo === 'CREDITO') {
            $vencimiento = ! empty($d['vencimiento'])
                ? Carbon::parse($d['vencimiento'])->startOfDay()
                : $fecha->copy()->addDays((int) config('compras.plazo_defecto_dias', 30));
            if ($vencimiento->lt($fecha)) {
                throw new NegocioException('El vencimiento no puede ser anterior a la fecha de la compra.');
            }
        }

        return DB::transaction(function () use ($d, $usuario, $tipo, $lineas, $fecha, $nroDoc, $vencimiento) {
            if ($nroDoc !== null && Compra::where('prov_id', $d['prov_id'])->where('com_nro_documento', $nroDoc)
                ->where('com_estado', '!=', 'ANULADA')->lockForUpdate()->exists()) {
                throw new NegocioException("Ya hay una compra a este proveedor con el documento {$nroDoc}. Revisá que no la estés cargando dos veces.");
            }

            $productos = Producto::whereIn('pro_id', array_keys($lineas))->lockForUpdate()->get()->keyBy('pro_id');
            foreach (array_keys($lineas) as $proId) {
                if (! $productos->has($proId)) {
                    throw new NegocioException('Uno de los productos ya no existe.');
                }
            }

            $total = 0.0;
            foreach ($lineas as $l) {
                $total += round($l['cantidad'] * $l['costo'], 2);
            }
            $total = round($total, 2);

            $compra = Compra::create([
                'prov_id' => $d['prov_id'],
                'usu_id' => $usuario->usu_id,
                'suc_id' => $d['suc_id'] ?? null,
                'com_fecha' => $fecha->copy()->setTimeFrom(now()),
                'com_nro_documento' => $nroDoc,
                'com_tipo' => $tipo,
                'com_total' => $total,
                'com_estado' => 'REGISTRADA',
                'com_observacion' => ! empty($d['observacion']) ? mb_substr(trim($d['observacion']), 0, 255) : null,
            ]);

            $avisos = [];
            foreach ($lineas as $proId => $l) {
                $producto = $productos[$proId];
                $subtotal = round($l['cantidad'] * $l['costo'], 2);

                DetalleCompra::create([
                    'com_id' => $compra->com_id, 'pro_id' => $proId, 'dco_cantidad' => $l['cantidad'],
                    'dco_costo' => $l['costo'], 'dco_subtotal' => $subtotal, 'dco_devuelta' => 0,
                ]);

                // El costo se calcula con el stock de ANTES de la compra.
                $costoNuevo = $this->nuevoCosto(
                    (float) $producto->pro_stockactual, (float) $producto->pro_preciocosto, $l['cantidad'], $l['costo']
                );

                $this->stock->mover(
                    $proId, 'COMPRA', $l['cantidad'],
                    'Compra Nro '.$compra->com_id.($nroDoc ? ' (doc. '.$nroDoc.')' : ''), 'compra:'.$compra->com_id
                );

                DB::table('productos')->where('pro_id', $proId)->update(['pro_preciocosto' => $costoNuevo]);

                if ($costoNuevo > (float) $producto->pro_precioventa && (float) $producto->pro_precioventa > 0) {
                    $avisos[] = "«{$producto->pro_nombre}»: el costo ({$costoNuevo}) pasó a ser mayor que el precio de venta ({$producto->pro_precioventa}). Revisá el precio.";
                }
            }

            $cuenta = CuentaPagar::create([
                'com_id' => $compra->com_id,
                'prov_id' => $compra->prov_id,
                'cpa_monto_total' => $total,
                'cpa_saldo_pendiente' => $total,
                'cpa_fecha_vencimiento' => $vencimiento?->toDateString(),
                'cpa_estado' => $total > 0 ? 'PENDIENTE' : 'PAGADA',
            ]);

            if ($tipo === 'CONTADO' && $total > 0) {
                $this->pagos->pagar(
                    $cuenta->cpa_id, $total, (string) ($d['forma_pago'] ?: 'EFECTIVO'),
                    $d['referencia'] ?? null, $usuario, isset($d['caj_id']) ? (int) $d['caj_id'] : null
                );
            }

            AuditoriaService::registrar('COMPRA_REGISTRADA', 'compras', $compra->com_id, [
                'proveedor' => $compra->prov_id, 'total' => $total, 'tipo' => $tipo, 'documento' => $nroDoc, 'lineas' => count($lineas),
            ]);

            return ['compra' => $compra, 'avisos' => $avisos];
        });
    }

    public function anular(int $comId, User $usuario, string $motivo): Compra
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new NegocioException('Indicá el motivo de la anulación de la compra.');
        }

        return DB::transaction(function () use ($comId, $usuario, $motivo) {
            $compra = Compra::whereKey($comId)->lockForUpdate()->first();
            if (! $compra) {
                throw new NegocioException('Compra no encontrada.');
            }
            if ($compra->com_estado !== 'REGISTRADA') {
                throw new NegocioException('Esta compra ya fue anulada.');
            }

            $detalles = DetalleCompra::where('com_id', $compra->com_id)->get();
            Producto::whereIn('pro_id', $detalles->pluck('pro_id'))->lockForUpdate()->get();

            // 1. La mercadería sale del stock (lo ya devuelto al proveedor no se saca dos veces).
            foreach ($detalles as $det) {
                $resta = round((float) $det->dco_cantidad - (float) $det->dco_devuelta, 2);
                if ($resta <= 0) {
                    continue;
                }
                try {
                    $this->stock->mover(
                        (int) $det->pro_id, 'ANULACION_COMPRA', -$resta,
                        'Anulación de compra Nro '.$compra->com_id, 'compra:'.$compra->com_id
                    );
                } catch (NegocioException $e) {
                    throw new NegocioException($e->getMessage().' Parte de esta mercadería ya se vendió: no se puede anular la compra completa. Usá "Devolver al proveedor" por lo que sí tenés, o un ajuste en Inventario.');
                }
            }

            // 2. Los pagos activos se anulan (si salió efectivo, vuelve a la caja) y la cuenta queda anulada.
            $cuenta = CuentaPagar::where('com_id', $compra->com_id)->lockForUpdate()->first();
            if ($cuenta) {
                foreach (PagoProveedor::where('cpa_id', $cuenta->cpa_id)->where('pag_estado', 'ACTIVO')->get() as $pago) {
                    $this->pagos->anular($pago->pag_id, $usuario, 'Anulación de la compra Nro '.$compra->com_id.': '.$motivo, false);
                }
                $cuenta->update(['cpa_saldo_pendiente' => 0, 'cpa_estado' => 'ANULADA']);
            }

            $compra->update([
                'com_estado' => 'ANULADA',
                'com_anulada_por' => $usuario->usu_id,
                'com_anulada_fecha' => now(),
                'com_motivo_anulacion' => mb_substr($motivo, 0, 255),
            ]);

            AuditoriaService::registrar('COMPRA_ANULADA', 'compras', $compra->com_id, [
                'total' => (float) $compra->com_total, 'motivo' => $motivo,
            ]);

            return $compra;
        });
    }

    /**
     * Devolución parcial de mercadería al proveedor.
     *
     * @param  array<int|string,float|string>  $items  dco_id => cantidad a devolver
     * @return array{valor: float, descuento_deuda: float, a_reclamar: float}
     */
    public function devolver(int $comId, array $items, User $usuario, ?string $motivo = null): array
    {
        $items = array_filter(array_map(fn ($c) => round((float) $c, 2), $items), fn ($c) => $c > 0);
        if ($items === []) {
            throw new NegocioException('Indicá la cantidad a devolver de al menos un producto.');
        }

        return DB::transaction(function () use ($comId, $items, $usuario, $motivo) {
            $compra = Compra::whereKey($comId)->lockForUpdate()->first();
            if (! $compra || $compra->com_estado !== 'REGISTRADA') {
                throw new NegocioException('La compra no existe o está anulada.');
            }

            $detalles = DetalleCompra::where('com_id', $compra->com_id)->lockForUpdate()->get()->keyBy('dco_id');
            Producto::whereIn('pro_id', $detalles->pluck('pro_id'))->lockForUpdate()->get();

            $valor = 0.0;
            foreach ($items as $dcoId => $cantidad) {
                $det = $detalles->get($dcoId);
                if (! $det) {
                    throw new NegocioException('Una de las líneas no pertenece a esta compra.');
                }

                $disponible = round((float) $det->dco_cantidad - (float) $det->dco_devuelta, 2);
                if ($cantidad > $disponible + 0.0001) {
                    throw new NegocioException('No se puede devolver más de lo que se compró (quedan '.$disponible.' por devolver).');
                }

                $this->stock->mover(
                    (int) $det->pro_id, 'DEVOLUCION_PROVEEDOR', -$cantidad,
                    'Devolución al proveedor, compra Nro '.$compra->com_id.($motivo ? ': '.$motivo : ''), 'compra:'.$compra->com_id
                );

                $det->update(['dco_devuelta' => round((float) $det->dco_devuelta + $cantidad, 2)]);
                $valor += round($cantidad * (float) $det->dco_costo, 2);
            }
            $valor = round($valor, 2);

            // La deuda baja por el valor devuelto; si ya se había pagado de más, queda algo a reclamar.
            $cuenta = CuentaPagar::where('com_id', $compra->com_id)->lockForUpdate()->first();
            $descuento = 0.0;
            if ($cuenta && $cuenta->cpa_estado !== 'ANULADA') {
                $saldoAntes = round((float) $cuenta->cpa_saldo_pendiente, 2);
                $descuento = min($valor, $saldoAntes);
                $saldo = round($saldoAntes - $descuento, 2);
                $cuenta->update([
                    'cpa_monto_total' => max(round((float) $cuenta->cpa_monto_total - $valor, 2), 0),
                    'cpa_saldo_pendiente' => $saldo,
                    'cpa_estado' => $saldo <= 0 ? 'PAGADA' : 'PENDIENTE',
                ]);
            }
            $reclamar = round($valor - $descuento, 2);

            AuditoriaService::registrar('COMPRA_DEVOLUCION', 'compras', $compra->com_id, [
                'valor' => $valor, 'descuento_deuda' => $descuento, 'a_reclamar_al_proveedor' => $reclamar, 'motivo' => $motivo,
            ]);

            return ['valor' => $valor, 'descuento_deuda' => $descuento, 'a_reclamar' => $reclamar];
        });
    }

    /** Une líneas repetidas del mismo producto (suma cantidades, costo ponderado) y descarta las vacías. */
    private function juntarLineas(array $items): array
    {
        $lineas = [];
        foreach ($items as $i) {
            $proId = (int) ($i['pro_id'] ?? 0);
            $cant = round((float) ($i['cantidad'] ?? 0), 2);
            $costo = round((float) ($i['costo'] ?? 0), 2);

            if ($proId <= 0) {
                continue;
            }
            if ($cant <= 0) {
                throw new NegocioException('Todas las cantidades deben ser mayores a cero.');
            }
            if ($costo < 0) {
                throw new NegocioException('El costo no puede ser negativo.');
            }

            if (isset($lineas[$proId])) {
                $suma = $lineas[$proId]['cantidad'] + $cant;
                $lineas[$proId]['costo'] = round(($lineas[$proId]['cantidad'] * $lineas[$proId]['costo'] + $cant * $costo) / $suma, 2);
                $lineas[$proId]['cantidad'] = $suma;
            } else {
                $lineas[$proId] = ['cantidad' => $cant, 'costo' => $costo];
            }
        }

        return $lineas;
    }

    private function nuevoCosto(float $stock, float $costoActual, float $cantidad, float $costoCompra): float
    {
        if (config('compras.costo') === 'ultimo') {
            return round($costoCompra, 2);
        }

        $base = max($stock, 0);
        if ($base <= 0 || $costoActual <= 0) {
            return round($costoCompra, 2);
        }

        return round(($base * $costoActual + $cantidad * $costoCompra) / ($base + $cantidad), 2);
    }
}
