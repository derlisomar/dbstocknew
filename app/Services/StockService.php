<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Producto;
use App\Models\StockMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde cambia el stock de un producto.
 * Cada cambio deja un movimiento con quién, cuándo y por qué, y el stock resultante.
 * Debe llamarse dentro de una transacción: el producto se bloquea mientras se cambia.
 */
class StockService
{
    public const TIPOS = [
        'SALDO_INICIAL', 'CARGA_INICIAL', 'VENTA', 'ANULACION_VENTA', 'DEVOLUCION',
        'INGRESO_MERCADERIA', 'AJUSTE_ENTRADA', 'AJUSTE_SALIDA', 'CONTEO',
        'COMPRA', 'ANULACION_COMPRA', 'DEVOLUCION_PROVEEDOR',
    ];

    /** Tipos que el usuario puede cargar a mano desde la pantalla de Inventario. */
    public const TIPOS_MANUALES = ['INGRESO_MERCADERIA', 'AJUSTE_ENTRADA', 'AJUSTE_SALIDA', 'CONTEO'];

    /**
     * @param  float  $cantidad  con signo: entrada (+) o salida (-)
     */
    public function mover(
        int $productoId,
        string $tipo,
        float $cantidad,
        ?string $motivo = null,
        ?string $referencia = null,
        bool $permitirNegativo = false
    ): StockMovimiento {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Tipo de movimiento de stock no válido: {$tipo}");
        }

        $producto = Producto::whereKey($productoId)->lockForUpdate()->first();
        if (! $producto) {
            throw new NegocioException('Producto no encontrado.');
        }

        $cantidad = round($cantidad, 2);
        $nuevo = round((float) $producto->pro_stockactual + $cantidad, 2);

        if ($nuevo < 0 && ! $permitirNegativo) {
            throw new NegocioException("«{$producto->pro_nombre}» tiene {$producto->pro_stockactual} en stock: no se puede sacar ".abs($cantidad).'.');
        }

        // Actualización directa (la columna ya no es de asignación masiva).
        DB::table('productos')->where('pro_id', $producto->pro_id)->update(['pro_stockactual' => $nuevo]);

        return StockMovimiento::create([
            'pro_id' => $producto->pro_id,
            'smo_tipo' => $tipo,
            'smo_cantidad' => $cantidad,
            'smo_stock_resultante' => $nuevo,
            'smo_motivo' => $motivo !== null ? mb_substr(trim($motivo), 0, 255) : null,
            'smo_referencia' => $referencia,
            'usu_id' => auth()->id(),
            'smo_fecha' => now(),
        ]);
    }

    /**
     * Movimiento manual desde la pantalla de Inventario.
     *
     * Para ENTRADA/SALIDA/AJUSTE la cantidad es positiva y el tipo decide el sentido.
     * Para CONTEO la cantidad es lo CONTADO en el estante: se registra la diferencia contra el sistema.
     */
    public function registrarManual(int $productoId, string $tipo, float $cantidad, string $motivo): StockMovimiento
    {
        if (! in_array($tipo, self::TIPOS_MANUALES, true)) {
            throw new NegocioException('Tipo de movimiento no válido.');
        }
        if (trim($motivo) === '') {
            throw new NegocioException('Indicá el motivo del movimiento.');
        }
        if ($cantidad < 0 || ($tipo !== 'CONTEO' && $cantidad <= 0)) {
            throw new NegocioException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($productoId, $tipo, $cantidad, $motivo) {
            if ($tipo === 'CONTEO') {
                $actual = (float) Producto::whereKey($productoId)->lockForUpdate()->value('pro_stockactual');
                $delta = round($cantidad - $actual, 2);
                $texto = trim($motivo).' (contado: '.$cantidad.', sistema: '.$actual.')';
            } else {
                $delta = $tipo === 'AJUSTE_SALIDA' ? -$cantidad : $cantidad;
                $texto = $motivo;
            }

            $mov = $this->mover($productoId, $tipo, $delta, $texto);

            AuditoriaService::registrar('STOCK_'.$tipo, 'productos', $productoId, [
                'cantidad' => $delta,
                'stock_resultante' => (float) $mov->smo_stock_resultante,
                'motivo' => $motivo,
            ]);

            return $mov;
        });
    }

    /**
     * Productos cuyo stock no coincide con la suma de sus movimientos
     * (alguien lo cambió directamente en la base, o hay un error).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function descuadres()
    {
        return DB::table('productos as p')
            ->leftJoin('stock_movimientos as m', 'm.pro_id', '=', 'p.pro_id')
            ->groupBy('p.pro_id', 'p.pro_nombre', 'p.pro_stockactual')
            ->havingRaw('ABS(COALESCE(p.pro_stockactual, 0) - COALESCE(SUM(m.smo_cantidad), 0)) > 0.001')
            ->get([
                'p.pro_id', 'p.pro_nombre', 'p.pro_stockactual',
                DB::raw('COALESCE(SUM(m.smo_cantidad), 0) as suma_movimientos'),
            ]);
    }
}
