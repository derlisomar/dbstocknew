<?php

namespace App\Support;

/**
 * Unidades en las que se vende un producto.
 * Se guarda UNA sola unidad por producto (la de control): unidad, kilogramo o litro.
 * Para kilos y litros el cajero puede escribir gramos o mililitros: el sistema convierte antes de guardar.
 */
class Unidades
{
    public const UNIDAD = 'UN';
    public const KILO = 'KG';
    public const LITRO = 'LT';

    /** @var array<string,array{nombre:string,corta:string,decimales:int,menor:?string,factor:int}> */
    private const DATOS = [
        'UN' => ['nombre' => 'Unidad', 'corta' => 'un', 'decimales' => 0, 'menor' => null, 'factor' => 1],
        'KG' => ['nombre' => 'Kilogramo', 'corta' => 'kg', 'decimales' => 3, 'menor' => 'g', 'factor' => 1000],
        'LT' => ['nombre' => 'Litro', 'corta' => 'L', 'decimales' => 3, 'menor' => 'ml', 'factor' => 1000],
    ];

    /** @return string[] */
    public static function codigos(): array
    {
        return array_keys(self::DATOS);
    }

    /** @return array<string,string> código => nombre */
    public static function nombres(): array
    {
        return array_map(fn ($d) => $d['nombre'], self::DATOS);
    }

    public static function normalizar(?string $codigo): string
    {
        $c = strtoupper(trim((string) $codigo));

        return isset(self::DATOS[$c]) ? $c : self::UNIDAD;
    }

    public static function decimales(?string $codigo): int
    {
        return self::DATOS[self::normalizar($codigo)]['decimales'];
    }

    public static function esFraccionable(?string $codigo): bool
    {
        return self::decimales($codigo) > 0;
    }

    /** Datos para el punto de venta (nombre corto, medida menor y factor de conversión). */
    public static function paraPantalla(): array
    {
        return self::DATOS;
    }

    /** ¿La cantidad es válida para esa unidad? (entera en unidades, hasta 3 decimales en kilos y litros) */
    public static function cantidadValida(?string $codigo, float $cantidad): bool
    {
        if ($cantidad <= 0) {
            return false;
        }
        $dec = self::decimales($codigo);

        return abs($cantidad - round($cantidad, $dec)) < 0.0000001;
    }

    /** Importe de una línea: en kilos y litros se redondea a guaraníes enteros; en unidades a 2 decimales como siempre. */
    public static function importe(float $precio, float $cantidad, ?string $codigo): float
    {
        return round($precio * $cantidad, self::esFraccionable($codigo) ? 0 : 2);
    }

    /** Redondea a los decimales que admite la unidad. */
    public static function redondear(?string $codigo, float $cantidad): float
    {
        return round($cantidad, self::decimales($codigo));
    }

    /** Texto para comprobantes: "3", "0,250 kg", "1,5 L". */
    public static function texto(float|int|string|null $cantidad, ?string $codigo = 'UN'): string
    {
        $c = (float) $cantidad;
        $u = self::DATOS[self::normalizar($codigo)];
        $t = $u['decimales'] === 0
            ? number_format($c, 0, ',', '.')
            : rtrim(rtrim(number_format($c, $u['decimales'], ',', '.'), '0'), ',');
        if ($t === '') {
            $t = '0';
        }

        return $u['decimales'] === 0 ? $t : $t.' '.$u['corta'];
    }
}
