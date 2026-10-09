<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Promocion;
use Illuminate\Support\Collection;

/**
 * Única fuente de verdad del precio de venta.
 * La usan la pantalla del PDV (para mostrar) y el servidor (para cobrar),
 * así el precio que ve el cajero es el mismo que se registra.
 *
 * Regla: precio mayorista (si el cliente es mayorista y el producto lo tiene)
 *        > precio promocional (si hay promoción vigente)
 *        > precio de venta normal.
 */
class PrecioService
{
    /** Promociones activas y vigentes hoy. Cargar una sola vez por request. */
    public function promocionesVigentes(): Collection
    {
        $hoy = now()->toDateString();

        return Promocion::where('prom_activa', true)
            ->where('prom_fecha_fin', '>=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('prom_fecha_inicio')->orWhere('prom_fecha_inicio', '<=', $hoy);
            })
            ->get();
    }

    /** Promoción que aplica al producto: primero la del producto, luego la de su categoría. */
    public function promocionDe(Producto $producto, Collection $promociones): ?Promocion
    {
        return $promociones->where('prom_aplica_a', 'PRODUCTO')->where('pro_id', $producto->pro_id)->first()
            ?? $promociones->where('prom_aplica_a', 'CATEGORIA')->where('cat_id', $producto->cat_id)->first();
    }

    /**
     * Precio con la promoción aplicada, o null si no hay promoción o si el descuento
     * dejaría el precio en cero o negativo (en ese caso la promoción se ignora).
     */
    public function precioPromocional(Producto $producto, ?Promocion $promocion): ?float
    {
        if (! $promocion) {
            return null;
        }

        $normal = (float) $producto->pro_precioventa;
        $valor = (float) $promocion->prom_valor;

        $precio = $promocion->prom_tipo_descuento === 'PORCENTAJE'
            ? $normal - ($normal * $valor / 100)
            : $normal - $valor;

        return $precio > 0 ? round($precio) : null;
    }

    /** Precio unitario final (en guaraníes) que debe pagar este cliente por este producto. */
    public function precioFinal(Producto $producto, bool $clienteEsMayorista, Collection $promociones): float
    {
        $normal = (float) $producto->pro_precioventa;
        $promo = $this->precioPromocional($producto, $this->promocionDe($producto, $promociones));
        $base = $promo ?? $normal;

        $mayorista = (float) $producto->pro_preciomayorista;

        return ($clienteEsMayorista && $mayorista > 0) ? $mayorista : $base;
    }
}
