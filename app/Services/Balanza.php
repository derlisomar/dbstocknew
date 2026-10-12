<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoCodigo;

/**
 * Interpreta lo que llega del lector de códigos en el punto de venta:
 *  1) etiqueta de balanza (peso o importe adentro del código),
 *  2) código de barras / código interno exacto de un producto.
 */
class Balanza
{
    /** Dígito verificador EAN-13 correcto. */
    public static function ean13Valido(string $codigo): bool
    {
        if (! preg_match('/^\d{13}$/', $codigo)) {
            return false;
        }
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $codigo[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return ((10 - $suma % 10) % 10) === (int) $codigo[12];
    }

    /**
     * Si el código es una etiqueta de balanza válida devuelve ['plu' => '000123', 'valor' => 250.0] (valor en gramos o Gs), si no null.
     *
     * @return array{plu:string,valor:int}|null
     */
    public static function leerEtiqueta(string $codigo): ?array
    {
        if (! config('balanza.activa') || ! self::ean13Valido($codigo)) {
            return null;
        }
        $prefijo = (string) config('balanza.prefijo');
        $dPlu = (int) config('balanza.digitos_plu');
        $dValor = 12 - strlen($prefijo) - $dPlu;
        if ($prefijo === '' || $dPlu < 1 || $dValor < 1 || ! str_starts_with($codigo, $prefijo)) {
            return null;
        }

        return [
            'plu' => substr($codigo, strlen($prefijo), $dPlu),
            'valor' => (int) substr($codigo, strlen($prefijo) + $dPlu, $dValor),
        ];
    }

    /**
     * Resuelve un código escaneado.
     *
     * @return array{tipo:string,producto:Producto,cantidad:float|null}|null
     *               tipo: 'balanza' (cantidad calculada) o 'barra' (cantidad null: la elige la pantalla)
     */
    public static function resolver(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        // Primero la etiqueta de balanza: si el PLU no existe cae a la búsqueda normal (podría ser un EAN real que empieza con 2).
        if ($e = self::leerEtiqueta($codigo)) {
            $plu = ltrim($e['plu'], '0');
            $p = Producto::where('pro_activo', true)->whereNotNull('pro_plu')->get()
                ->first(fn ($x) => ltrim((string) $x->pro_plu, '0') === $plu);
            if ($p) {
                if (config('balanza.valor') === 'PRECIO') {
                    $precio = (float) $p->pro_precioventa;
                    $cantidad = $precio > 0 ? round($e['valor'] / $precio, 3) : 0.0;
                } else {
                    $cantidad = round($e['valor'] / 1000, 3);   // gramos o mililitros -> kilos o litros
                }
                if (! \App\Support\Unidades::esFraccionable($p->pro_unidad)) {
                    $cantidad = (float) max(1, round($e['valor']));   // producto por unidad con etiqueta: el valor ya es la cantidad
                }

                return ['tipo' => 'balanza', 'producto' => $p, 'cantidad' => $cantidad];
            }
        }

        $p = Producto::where('pro_activo', true)->where('pro_codigo', $codigo)->first();
        if (! $p) {
            $pid = ProductoCodigo::where('cod_codigo', $codigo)->value('pro_id');
            $p = $pid ? Producto::where('pro_activo', true)->find($pid) : null;
        }

        return $p ? ['tipo' => 'barra', 'producto' => $p, 'cantidad' => null] : null;
    }
}
