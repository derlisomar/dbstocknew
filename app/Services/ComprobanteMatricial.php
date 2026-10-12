<?php

namespace App\Services;

use App\Models\Venta;
use Carbon\Carbon;

/**
 * Arma el comprobante de una venta como texto de 80 columnas para la impresora matricial.
 * Devuelve una lista de líneas ya alineadas (cada una de 80 caracteres como máximo).
 */
class ComprobanteMatricial
{
    public const ANCHO = 80;

    // Columnas del detalle: cantidad, descripción, precio y total (más 3 espacios = 80)
    private const C_CANT = 9;
    private const C_DESC = 40;
    private const C_PREC = 12;
    private const C_TOT = 16;

    /** @return list<string> */
    public static function lineas(Venta $venta, bool $factura, bool $copia = false): array
    {
        $venta->loadMissing(['detalles.producto', 'cliente', 'sucursal', 'usuario', 'sesion.caja']);
        $suc = $venta->sucursal;
        $o = [];

        if ($copia) {
            $o[] = self::centrar('*** COPIA / REIMPRESION ***');
        }

        $o[] = self::centrar(mb_strtoupper((string) ConfiguracionService::get('negocio_nombre', 'MI EMPRESA S.A.')));
        $o[] = self::centrar('RUC: '.ConfiguracionService::get('negocio_ruc', '80001234-5'));
        foreach ([$suc?->suc_actividad_economica, $suc?->suc_direccion, $suc?->suc_telefono ? 'TELEFONO: '.$suc->suc_telefono : null] as $texto) {
            if (filled($texto)) {
                $o[] = self::centrar((string) $texto);
            }
        }
        $o[] = self::linea();

        $fecha = Carbon::parse($venta->vta_fecha)->format('d/m/Y H:i');
        if ($factura) {
            $o[] = self::par('TIMBRADO: '.$venta->vta_timbrado, 'FACTURA NRO: '.$suc?->suc_est_punto_exp.'-'.str_pad((string) $venta->vta_nro_factura, 7, '0', STR_PAD_LEFT));
            $inicio = $suc?->suc_timbrado_inicio ? Carbon::parse($suc->suc_timbrado_inicio)->format('d/m/Y') : '';
            $fin = $suc?->suc_timbrado_fin ? Carbon::parse($suc->suc_timbrado_fin)->format('d/m/Y') : '';
            $o[] = self::par('VIGENCIA: '.$inicio.' AL '.$fin, 'CONDICION: '.$venta->vta_tipo);
            $o[] = self::par('FECHA: '.$fecha, 'CAJA: '.($venta->sesion?->caja?->caj_id ?? 1));
        } else {
            $o[] = self::par('CONDICION: '.$venta->vta_tipo, 'OPERACION NRO: '.str_pad((string) $venta->vta_id, 10, '0', STR_PAD_LEFT));
            $o[] = self::par('FECHA: '.$fecha, 'CAJA: '.($venta->sesion?->caja?->caj_id ?? 1));
        }
        $o[] = self::linea();
        $o[] = 'CI. / RUC.: '.($venta->cliente->cli_ruc_ci ?? '44444401-7');
        $o[] = 'CLIENTE   : '.($venta->cliente->cli_nombre ?? 'CLIENTE OCASIONAL');
        $o[] = self::linea();

        $o[] = self::fila('CANT.', 'DESCRIPCION', 'PRECIO UNIT.', 'TOTAL');
        $o[] = self::linea();
        foreach ($venta->detalles as $d) {
            $nombre = trim(($d->producto->pro_codigo ?? '').' '.($d->producto->pro_nombre ?? ''));
            $partes = self::partir($nombre, self::C_DESC);
            $o[] = self::fila(\App\Support\Unidades::texto($d->det_cantidad, $d->producto->pro_unidad ?? 'UN'), array_shift($partes), self::gs($d->det_preciounitario), self::gs($d->det_subtotal));
            foreach ($partes as $resto) {
                $o[] = self::fila('', $resto, '', '');
            }
        }
        $o[] = self::linea();

        $o[] = self::derecha((($factura ? 'TOTAL A PAGAR' : 'TOTAL').': Gs. '.self::gs($venta->vta_total)));

        if ($factura) {
            $o[] = self::linea();
            $o[] = self::columnas3('SUB TOTAL', 'LIQUIDACION', 'IVA');
            $o[] = self::columnas3('Exentas:', self::gs($venta->vta_total_exenta), '0');
            $o[] = self::columnas3('5%:', self::gs($venta->vta_total_iva5 * 21), self::gs($venta->vta_total_iva5));
            $o[] = self::columnas3('10%:', self::gs($venta->vta_total_iva10 * 11), self::gs($venta->vta_total_iva10));
            $o[] = self::columnas3('TOTAL IVA:', '', self::gs($venta->vta_total_iva5 + $venta->vta_total_iva10));
        }

        $o[] = self::linea();
        $o[] = 'ATENDIDO POR: '.($venta->usuario->usu_nombre ?? '');
        $o[] = self::centrar((string) ConfiguracionService::get('negocio_pie_ticket', '***GRACIAS POR SU COMPRA***'));
        if ($factura) {
            $o[] = self::centrar('ORIGINAL: CLIENTE - COMPRADOR');
        } else {
            $o[] = self::centrar('*NO VALIDO COMO FACTURA* - *USO INTERNO*');
        }

        return array_map(fn (string $l) => self::cortar($l, self::ANCHO), $o);
    }

    // ------------------------------------------------------------------ helpers

    private static function linea(): string
    {
        return str_repeat('-', self::ANCHO);
    }

    private static function gs(float|int|string|null $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    private static function cantidad(float|int|string|null $n): string
    {
        $t = rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');

        return $t === '' ? '0' : $t;
    }

    private static function largo(string $s): int
    {
        return mb_strlen($s);
    }

    private static function cortar(string $s, int $n): string
    {
        return self::largo($s) > $n ? mb_substr($s, 0, $n) : $s;
    }

    private static function izq(string $s, int $n): string
    {
        $s = self::cortar($s, $n);

        return $s.str_repeat(' ', $n - self::largo($s));
    }

    private static function der(string $s, int $n): string
    {
        $s = self::cortar($s, $n);

        return str_repeat(' ', $n - self::largo($s)).$s;
    }

    private static function centrar(string $s): string
    {
        $s = self::cortar($s, self::ANCHO);

        return str_repeat(' ', intdiv(self::ANCHO - self::largo($s), 2)).$s;
    }

    private static function derecha(string $s): string
    {
        return self::der($s, self::ANCHO);
    }

    /** Dos textos en la misma línea: uno a la izquierda y otro a la derecha. */
    private static function par(string $izq, string $der): string
    {
        $der = self::cortar($der, 36);
        $izq = self::cortar($izq, self::ANCHO - self::largo($der) - 1);

        return $izq.str_repeat(' ', self::ANCHO - self::largo($izq) - self::largo($der)).$der;
    }

    private static function fila(string $cant, string $desc, string $prec, string $tot): string
    {
        return self::der($cant, self::C_CANT).' '.self::izq($desc, self::C_DESC).' '.self::der($prec, self::C_PREC).' '.self::der($tot, self::C_TOT);
    }

    private static function columnas3(string $a, string $b, string $c): string
    {
        return self::izq($a, 30).self::der($b, 25).self::der($c, 25);
    }

    /** Parte un texto largo en varias líneas sin cortar palabras (salvo que una sola palabra no entre). @return list<string> */
    private static function partir(string $texto, int $ancho): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }

        $lineas = [];
        $actual = '';
        foreach (explode(' ', $texto) as $palabra) {
            while (self::largo($palabra) > $ancho) {
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual = '';
                }
                $lineas[] = mb_substr($palabra, 0, $ancho);
                $palabra = mb_substr($palabra, $ancho);
            }
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;
            if (self::largo($prueba) <= $ancho) {
                $actual = $prueba;
            } else {
                $lineas[] = $actual;
                $actual = $palabra;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }
}
