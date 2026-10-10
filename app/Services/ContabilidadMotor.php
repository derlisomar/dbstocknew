<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Motor de asientos automáticos.
 *
 * No toca ninguna operación del sistema: LEE lo que ya pasó (ventas, cobros, compras, pagos, devoluciones,
 * ingresos y egresos, cierres de caja, ajustes de stock) y genera el asiento que le corresponde.
 * Así una falla contable nunca impide vender o cobrar, y lo que quede pendiente se contabiliza en la
 * próxima pasada (cada pocos minutos, o al abrir la pantalla de Contabilidad).
 *
 * Es idempotente: cada asiento lleva una clave única (VENTA:15, COBRO:7...) y no se duplica.
 */
class ContabilidadMotor
{
    private const LOTE = 300;

    public function __construct(private ContabilidadService $c)
    {
    }

    /** @return array{generados:int, errores:list<string>} */
    public function sincronizar(): array
    {
        $r = ['generados' => 0, 'errores' => []];

        if (! ContabilidadService::habilitada() || ! $this->c->preparada()) {
            return $r;
        }

        // Si otra pasada está corriendo, esta no hace nada.
        $cerrojo = null;
        try {
            $cerrojo = Cache::lock('contabilidad_sync', 120);
            if (! $cerrojo->get()) {
                return $r;
            }
        } catch (\Throwable) {
            $cerrojo = null;
        }

        try {
            $this->c->olvidarCache();
            $inicio = $this->c->inicio()->toDateString();

            $this->correr('Venta', $this->ids('ventas', 'v', 'vta_id', 'VENTA', ['v.vta_estado' => 'CONFIRMADA'], 'v.vta_fecha', $inicio), fn ($id) => $this->venta($id), $r);
            $this->correr('Anulación de venta', $this->idsAnulados('ventas', 'v', 'vta_id', 'vta_estado', 'ANULADA', 'VENTA'), fn ($id) => $this->anularVenta($id), $r);
            $this->correr('Devolución', $this->idsDevoluciones($inicio), fn ($id) => $this->devolucion($id), $r);
            $this->correr('Cobranza', $this->ids('cobranzas', 'c', 'cob_id', 'COBRO', ['c.cob_estado' => 'ACTIVA'], 'c.cob_fecha', $inicio), fn ($id) => $this->cobranza($id), $r);
            $this->correr('Anulación de cobro', $this->idsAnulados('cobranzas', 'c', 'cob_id', 'cob_estado', 'ANULADA', 'COBRO'), fn ($id) => $this->anularSimple('COBRO', $id, 'cobranzas', 'cob_id', 'cob_anulada_fecha', 'Anulación del cobro N° '), $r);
            $this->correr('Compra', $this->ids('compras', 'c', 'com_id', 'COMPRA', ['c.com_estado' => 'REGISTRADA'], 'c.com_fecha', $inicio), fn ($id) => $this->compra($id), $r);
            $this->correr('Anulación de compra', $this->idsAnulados('compras', 'c', 'com_id', 'com_estado', 'ANULADA', 'COMPRA'), fn ($id) => $this->anularCompra($id), $r);
            $this->correr('Pago a proveedor', $this->ids('pagos_proveedores', 'p', 'pag_id', 'PAGO', ['p.pag_estado' => 'ACTIVO'], 'p.pag_fecha', $inicio), fn ($id) => $this->pagoProveedor($id), $r);
            $this->correr('Anulación de pago', $this->idsAnulados('pagos_proveedores', 'p', 'pag_id', 'pag_estado', 'ANULADO', 'PAGO'), fn ($id) => $this->anularSimple('PAGO', $id, 'pagos_proveedores', 'pag_id', 'pag_anulado_fecha', 'Anulación del pago N° '), $r);
            $this->correr('Ingreso/egreso de caja', $this->ids('ingresos_egresos', 'i', 'ie_id', 'IE', [], 'i.ie_fecha', $inicio), fn ($id) => $this->ingresoEgreso($id), $r);

            $this->cierres($inicio, $r);
            $this->stock($inicio, $r);
        } finally {
            optional($cerrojo)->release();
        }

        ConfiguracionService::set(['cont_ultima_sync' => now()->toDateTimeString()]);

        return $r;
    }

    /** Cuántas operaciones esperan ser contabilizadas (para el panel). */
    public function pendientes(): int
    {
        if (! $this->c->preparada()) {
            return 0;
        }
        $inicio = $this->c->inicio()->toDateString();

        return count($this->ids('ventas', 'v', 'vta_id', 'VENTA', ['v.vta_estado' => 'CONFIRMADA'], 'v.vta_fecha', $inicio, 5000))
            + count($this->ids('cobranzas', 'c', 'cob_id', 'COBRO', ['c.cob_estado' => 'ACTIVA'], 'c.cob_fecha', $inicio, 5000))
            + count($this->ids('compras', 'c', 'com_id', 'COMPRA', ['c.com_estado' => 'REGISTRADA'], 'c.com_fecha', $inicio, 5000))
            + count($this->ids('pagos_proveedores', 'p', 'pag_id', 'PAGO', ['p.pag_estado' => 'ACTIVO'], 'p.pag_fecha', $inicio, 5000));
    }

    // ------------------------------------------------------------------ ejecución

    private function correr(string $etiqueta, array $ids, callable $hacer, array &$r): void
    {
        foreach ($ids as $id) {
            try {
                if (DB::transaction(fn () => $hacer((int) $id))) {
                    $r['generados']++;
                }
            } catch (\Throwable $e) {
                Log::warning("Contabilidad: no se pudo contabilizar {$etiqueta} #{$id}: ".$e->getMessage());
                $r['errores'][] = "{$etiqueta} #{$id}: ".$e->getMessage();
            }
        }
    }

    /** Operaciones activas que todavía no tienen asiento. */
    private function ids(string $tabla, string $a, string $pk, string $prefijo, array $donde, string $colFecha, string $inicio, int $lim = self::LOTE): array
    {
        $q = DB::table("{$tabla} as {$a}")
            ->leftJoin('cont_asientos as x', 'x.asi_clave', '=', DB::raw("'{$prefijo}:' || {$a}.{$pk}"))
            ->whereNull('x.asi_id')
            ->whereDate($colFecha, '>=', $inicio);

        foreach ($donde as $col => $val) {
            $q->where($col, $val);
        }

        return $q->orderBy("{$a}.{$pk}")->limit($lim)->pluck("{$a}.{$pk}")->all();
    }

    /** Operaciones anuladas que tienen asiento pero todavía no su contra-asiento. */
    private function idsAnulados(string $tabla, string $a, string $pk, string $estadoCol, string $valor, string $prefijo): array
    {
        return DB::table("{$tabla} as {$a}")
            ->join('cont_asientos as o', 'o.asi_clave', '=', DB::raw("'{$prefijo}:' || {$a}.{$pk}"))
            ->leftJoin('cont_asientos as r', 'r.asi_clave', '=', DB::raw("'{$prefijo}_ANUL:' || {$a}.{$pk}"))
            ->whereNull('r.asi_id')
            ->where("{$a}.{$estadoCol}", $valor)
            ->orderBy("{$a}.{$pk}")->limit(self::LOTE)->pluck("{$a}.{$pk}")->all();
    }

    private function idsDevoluciones(string $inicio): array
    {
        return DB::table('devoluciones as d')
            ->join('ventas as v', 'v.vta_id', '=', 'd.vta_id')
            ->leftJoin('cont_asientos as x', 'x.asi_clave', '=', DB::raw("'DEV:' || d.dev_id"))
            ->whereNull('x.asi_id')
            ->where('v.vta_estado', 'CONFIRMADA')
            ->whereDate('d.dev_fecha', '>=', $inicio)
            ->orderBy('d.dev_id')->limit(self::LOTE)->pluck('d.dev_id')->all();
    }

    // ------------------------------------------------------------------ utilidades

    private static function iva(float $bruto, int $tasa): float
    {
        return round(match ($tasa) {
            10 => $bruto / 11,
            5 => $bruto / 21,
            default => 0.0,
        }, 2);
    }

    private static function sumar(array &$arr, int $cuenta, float $valor): void
    {
        $arr[$cuenta] = round(($arr[$cuenta] ?? 0) + $valor, 2);
    }

    /** @param array<int,float> $arr @return list<array{0:int,1:float,2:float}> */
    private static function comoDebe(array $arr): array
    {
        return array_map(fn ($cue, $v) => [$cue, $v, 0.0], array_keys($arr), $arr);
    }

    /** @param array<int,float> $arr */
    private static function comoHaber(array $arr): array
    {
        return array_map(fn ($cue, $v) => [$cue, 0.0, $v], array_keys($arr), $arr);
    }

    private static function fecha($valor): string
    {
        return Carbon::parse($valor ?: now())->toDateString();
    }

    /** Mueve la diferencia (por redondeos o descuentos) a la cuenta de mayor importe. */
    private static function ajustarMayor(array &$arr, float $diferencia): void
    {
        if (abs($diferencia) < 0.005 || $arr === []) {
            return;
        }
        arsort($arr);
        $k = array_key_first($arr);
        $arr[$k] = round($arr[$k] + $diferencia, 2);
    }

    private function nroVenta(object $v): string
    {
        return (string) ($v->vta_nro_factura ?: $v->vta_id);
    }

    // ------------------------------------------------------------------ ventas

    private function venta(int $id): bool
    {
        $v = DB::table('ventas')->where('vta_id', $id)->first();
        if (! $v || $v->vta_estado !== 'CONFIRMADA') {
            return false;
        }

        $det = DB::table('detalle_ventas as d')
            ->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->where('d.vta_id', $id)
            ->get(['d.det_cantidad', 'd.det_subtotal', 'd.det_preciocosto', 'p.pro_tipo_iva', 'p.cat_id']);

        $total = round((float) $v->vta_total, 2);
        $ingresos = [];
        $iva = 0.0;
        $bruto = 0.0;
        $costoDebe = [];
        $costoHaber = [];

        foreach ($det as $d) {
            $b = round((float) $d->det_subtotal, 2);
            $i = self::iva($b, (int) $d->pro_tipo_iva);
            self::sumar($ingresos, $this->c->cuentaDeCategoria('CAT_INGRESO', $d->cat_id ? (int) $d->cat_id : null), $b - $i);
            $iva += $i;
            $bruto += $b;

            $costo = round((float) $d->det_cantidad * (float) $d->det_preciocosto, 2);
            if ($costo > 0) {
                self::sumar($costoDebe, $this->c->cuentaDeCategoria('CAT_COSTO', $d->cat_id ? (int) $d->cat_id : null), $costo);
                self::sumar($costoHaber, $this->c->cuentaDeCategoria('CAT_INVENTARIO', $d->cat_id ? (int) $d->cat_id : null), $costo);
            }
        }

        if ($ingresos === []) {
            // Venta sin detalle (dato antiguo): se toma el IVA de la cabecera.
            $iva = round((float) $v->vta_total_iva10 + (float) $v->vta_total_iva5, 2);
            $ingresos[$this->c->cuentaDeCategoria('CAT_INGRESO', null)] = round($total - $iva, 2);
            $bruto = $total;
        }

        // Descuentos o redondeos: la diferencia se acomoda en el ingreso mayor para que el asiento cuadre.
        self::ajustarMayor($ingresos, round($total - round($bruto, 2), 2));

        $debe = $v->vta_tipo === 'CREDITO'
            ? $this->c->cuentaPorClave('clientes')
            : $this->c->cuentaDePago($v->vta_formapago, (string) ($v->vta_moneda ?: 'GS'));

        $lineas = array_merge(
            [[$debe, $total, 0.0, 'Venta '.$this->nroVenta($v)]],
            self::comoHaber($ingresos),
            $iva > 0 ? [[$this->c->cuentaPorClave('iva_debito'), 0.0, round($iva, 2), 'IVA de la venta']] : []
        );

        $fecha = self::fecha($v->vta_fecha);
        $tipo = $v->vta_tipo === 'CREDITO' ? 'a crédito' : 'contado';

        $this->c->crearAsiento($fecha, "Venta {$tipo} N° ".$this->nroVenta($v), 'VENTA', 'VENTA:'.$id, $lineas);

        if ($costoDebe !== []) {
            $this->c->crearAsiento(
                $fecha, 'Costo de la mercadería vendida (venta N° '.$this->nroVenta($v).')', 'COSTO_VENTA', 'CMV:'.$id,
                array_merge(self::comoDebe($costoDebe), self::comoHaber($costoHaber))
            );
        }

        return true;
    }

    private function reversarClave(string $clave, string $claveAnul, string $fecha, string $glosa, string $origen): bool
    {
        $asi = DB::table('cont_asientos')->where('asi_clave', $clave)->value('asi_id');
        if (! $asi || DB::table('cont_asientos')->where('asi_clave', $claveAnul)->exists()) {
            return false;
        }

        return (bool) $this->c->reversar((int) $asi, $claveAnul, $fecha, $glosa, $origen);
    }

    private function anularVenta(int $id): bool
    {
        $v = DB::table('ventas')->where('vta_id', $id)->first();
        $fecha = self::fecha($v->vta_anulada_fecha ?? null);
        $nro = $this->nroVenta($v);

        $this->reversarClave('VENTA:'.$id, 'VENTA_ANUL:'.$id, $fecha, "Anulación de la venta N° {$nro}", 'VENTA');
        $this->reversarClave('CMV:'.$id, 'CMV_ANUL:'.$id, $fecha, "Anulación del costo de la venta N° {$nro}", 'COSTO_VENTA');

        // Las devoluciones ya contabilizadas de esa venta se revierten también: la venta queda en cero.
        foreach (DB::table('devoluciones')->where('vta_id', $id)->pluck('dev_id') as $dev) {
            $this->reversarClave('DEV:'.$dev, 'DEV_ANUL:'.$dev, $fecha, "Anulación de la devolución N° {$dev} (venta anulada)", 'DEVOLUCION');
            $this->reversarClave('DEV_CMV:'.$dev, 'DEV_CMV_ANUL:'.$dev, $fecha, "Anulación del costo de la devolución N° {$dev}", 'COSTO_VENTA');
        }

        return true;
    }

    private function devolucion(int $id): bool
    {
        $d = DB::table('devoluciones')->where('dev_id', $id)->first();
        $v = DB::table('ventas')->where('vta_id', $d->vta_id)->first();

        $items = DB::table('detalle_devoluciones as dd')
            ->join('productos as p', 'p.pro_id', '=', 'dd.pro_id')
            ->leftJoin('detalle_ventas as dv', 'dv.det_vta_id', '=', 'dd.det_vta_id')
            ->where('dd.dev_id', $id)
            ->get(['dd.ddv_cantidad', 'dd.ddv_subtotal', 'dv.det_preciocosto', 'p.pro_tipo_iva', 'p.cat_id']);

        $total = round((float) $d->dev_total, 2);
        $ingresos = [];
        $iva = 0.0;
        $bruto = 0.0;
        $costoDebe = [];
        $costoHaber = [];

        foreach ($items as $it) {
            $b = round((float) $it->ddv_subtotal, 2);
            $i = self::iva($b, (int) $it->pro_tipo_iva);
            $cat = $it->cat_id ? (int) $it->cat_id : null;
            self::sumar($ingresos, $this->c->cuentaDeCategoria('CAT_INGRESO', $cat), $b - $i);
            $iva += $i;
            $bruto += $b;

            $costo = round((float) $it->ddv_cantidad * (float) ($it->det_preciocosto ?? 0), 2);
            if ($costo > 0) {
                self::sumar($costoDebe, $this->c->cuentaDeCategoria('CAT_INVENTARIO', $cat), $costo);
                self::sumar($costoHaber, $this->c->cuentaDeCategoria('CAT_COSTO', $cat), $costo);
            }
        }

        if ($ingresos === []) {
            return false;
        }
        self::ajustarMayor($ingresos, round($total - round($bruto, 2), 2));

        $contra = $v->vta_tipo === 'CREDITO'
            ? $this->c->cuentaPorClave('clientes')
            : $this->c->cuentaDePago($v->vta_formapago, (string) ($v->vta_moneda ?: 'GS'));

        $this->c->crearAsiento(
            self::fecha($d->dev_fecha), 'Devolución N° '.$id.' de la venta N° '.$this->nroVenta($v), 'DEVOLUCION', 'DEV:'.$id,
            array_merge(
                self::comoDebe($ingresos),
                $iva > 0 ? [[$this->c->cuentaPorClave('iva_debito'), round($iva, 2), 0.0, 'IVA de la devolución']] : [],
                [[$contra, 0.0, $total, 'Devolución al cliente']]
            )
        );

        if ($costoDebe !== []) {
            $this->c->crearAsiento(
                self::fecha($d->dev_fecha), 'Mercadería devuelta al stock (devolución N° '.$id.')', 'COSTO_VENTA', 'DEV_CMV:'.$id,
                array_merge(self::comoDebe($costoDebe), self::comoHaber($costoHaber))
            );
        }

        return true;
    }

    // ------------------------------------------------------------------ cobranzas

    private function cobranza(int $id): bool
    {
        $c = DB::table('cobranzas')->where('cob_id', $id)->first();
        if (! $c || $c->cob_estado !== 'ACTIVA') {
            return false;
        }

        $total = round((float) $c->cob_monto_total, 2);
        $fecha = Carbon::parse($c->cob_fecha);
        $movs = DB::table('caja_movimientos')->where('cob_id', $id)->get(['mov_tipo', 'mov_monto', 'mov_moneda']);
        $moneda = strtoupper((string) ($movs->first()->mov_moneda ?? 'GS')) ?: 'GS';

        $clientes = $this->c->cuentaPorClave('clientes');
        $lineas = [];

        if ($moneda !== 'GS') {
            // Cobro en moneda extranjera: se valúa a la cotización del día y la diferencia contra la deuda es diferencia de cambio.
            $neto = 0.0;
            foreach ($movs as $m) {
                $neto += $m->mov_tipo === 'INGRESO' ? (float) $m->mov_monto : -(float) $m->mov_monto;
            }
            $gs = round($neto * $this->c->tasa($moneda, $fecha), 2);
            $lineas[] = [$this->c->cuentaDePago($c->cob_formapago, $moneda), $gs, 0.0, "Cobro en {$moneda}"];
            $lineas[] = [$clientes, 0.0, $total, 'Cobro de deuda de cliente'];
            $dif = round($gs - $total, 2);
            if ($dif > 0) {
                $lineas[] = [$this->c->cuentaPorClave('dif_cambio_ganancia'), 0.0, $dif, 'Diferencia de cambio al cobrar'];
            } elseif ($dif < 0) {
                $lineas[] = [$this->c->cuentaPorClave('dif_cambio_perdida'), -$dif, 0.0, 'Diferencia de cambio al cobrar'];
            }
        } else {
            $lineas[] = [$this->c->cuentaDePago($c->cob_formapago, 'GS'), $total, 0.0, 'Cobro'];
            $lineas[] = [$clientes, 0.0, $total, 'Cobro de deuda de cliente'];
        }

        $this->c->crearAsiento($fecha->toDateString(), 'Cobranza N° '.$id, 'COBRANZA', 'COBRO:'.$id, $lineas);

        return true;
    }

    /** Anulación de un cobro o de un pago: se invierte el asiento original. */
    private function anularSimple(string $prefijo, int $id, string $tabla, string $pk, string $colFecha, string $glosa): bool
    {
        $fila = DB::table($tabla)->where($pk, $id)->first();

        return $this->reversarClave(
            $prefijo.':'.$id, $prefijo.'_ANUL:'.$id, self::fecha($fila->{$colFecha} ?? null),
            $glosa.$id, $prefijo === 'COBRO' ? 'COBRANZA' : 'PAGO_PROVEEDOR'
        );
    }

    // ------------------------------------------------------------------ compras y pagos

    private function compra(int $id): bool
    {
        $c = DB::table('compras')->where('com_id', $id)->first();
        if (! $c || $c->com_estado !== 'REGISTRADA') {
            return false;
        }

        $det = DB::table('detalle_compras as d')
            ->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->where('d.com_id', $id)
            ->get(['d.dco_subtotal', 'p.pro_tipo_iva', 'p.cat_id']);

        $total = round((float) $c->com_total, 2);
        $inventario = [];
        $iva = 0.0;
        $bruto = 0.0;

        foreach ($det as $d) {
            $b = round((float) $d->dco_subtotal, 2);
            $i = self::iva($b, (int) $d->pro_tipo_iva);
            self::sumar($inventario, $this->c->cuentaDeCategoria('CAT_INVENTARIO', $d->cat_id ? (int) $d->cat_id : null), $b - $i);
            $iva += $i;
            $bruto += $b;
        }

        if ($inventario === [] || $total <= 0) {
            return false;
        }
        self::ajustarMayor($inventario, round($total - round($bruto, 2), 2));

        $doc = $c->com_nro_documento ? ' (doc. '.$c->com_nro_documento.')' : '';
        $this->c->crearAsiento(
            self::fecha($c->com_fecha), "Compra N° {$id}{$doc}", 'COMPRA', 'COMPRA:'.$id,
            array_merge(
                self::comoDebe($inventario),
                $iva > 0 ? [[$this->c->cuentaPorClave('iva_credito'), round($iva, 2), 0.0, 'IVA crédito de la compra']] : [],
                [[$this->c->cuentaPorClave('proveedores'), 0.0, $total, 'Deuda con el proveedor']]
            )
        );

        return true;
    }

    private function anularCompra(int $id): bool
    {
        $c = DB::table('compras')->where('com_id', $id)->first();
        $fecha = self::fecha($c->com_anulada_fecha ?? null);

        $this->reversarClave('COMPRA:'.$id, 'COMPRA_ANUL:'.$id, $fecha, "Anulación de la compra N° {$id}", 'COMPRA');

        // Devoluciones al proveedor ya contabilizadas: también se revierten, la compra queda en cero.
        foreach (DB::table('stock_movimientos')->where('smo_tipo', 'DEVOLUCION_PROVEEDOR')->where('smo_referencia', 'compra:'.$id)->pluck('smo_id') as $smo) {
            $this->reversarClave('DEVPROV:'.$smo, 'DEVPROV_ANUL:'.$smo, $fecha, "Anulación de devolución al proveedor (compra N° {$id} anulada)", 'COMPRA');
        }

        return true;
    }

    private function pagoProveedor(int $id): bool
    {
        $p = DB::table('pagos_proveedores')->where('pag_id', $id)->first();
        if (! $p || $p->pag_estado !== 'ACTIVO') {
            return false;
        }

        $monto = round((float) $p->pag_monto, 2);
        $this->c->crearAsiento(
            self::fecha($p->pag_fecha), 'Pago a proveedor N° '.$id.' ('.$p->pag_forma_pago.')', 'PAGO_PROVEEDOR', 'PAGO:'.$id,
            [
                [$this->c->cuentaPorClave('proveedores'), $monto, 0.0, 'Pago de deuda'],
                [$this->c->cuentaDePago($p->pag_forma_pago, 'GS'), 0.0, $monto, 'Salida de dinero'],
            ]
        );

        return true;
    }

    // ------------------------------------------------------------------ caja

    private function ingresoEgreso(int $id): bool
    {
        $m = DB::table('ingresos_egresos')->where('ie_id', $id)->first();
        $monto = round((float) ($m->ie_monto ?? 0), 2);
        if (! $m || $monto <= 0) {
            return false;
        }

        $caja = $this->c->cuentaDePago('EFECTIVO', 'GS');
        $esIngreso = $m->ie_tipo === 'INGRESO';
        $otra = $this->c->cuentaPorClave($esIngreso ? 'otros_ingresos' : 'gastos_generales');
        $texto = mb_substr((string) $m->ie_concepto, 0, 150);

        $this->c->crearAsiento(
            self::fecha($m->ie_fecha), ($esIngreso ? 'Ingreso de caja: ' : 'Egreso de caja: ').$texto, 'CAJA', 'IE:'.$id,
            $esIngreso
                ? [[$caja, $monto, 0.0, null], [$otra, 0.0, $monto, null]]
                : [[$otra, $monto, 0.0, null], [$caja, 0.0, $monto, null]]
        );

        return true;
    }

    /**
     * Cierre de caja: faltante o sobrante contado contra lo esperado, y diferencia de cambio del efectivo
     * en dólares y reales (valuado a la cotización de cada movimiento contra la del cierre).
     */
    private function cierres(string $inicio, array &$r): void
    {
        $marca = ConfiguracionService::get('cont_marca_cierres') ?: $inicio.' 00:00:00';

        $sesiones = DB::table('caja_sesiones')
            ->where('ses_estado', 'CERRADA')
            ->whereNotNull('ses_fecha_cierre')
            ->where('ses_fecha_cierre', '>=', $marca)
            ->whereDate('ses_fecha_cierre', '>=', $inicio)
            ->orderBy('ses_fecha_cierre')->orderBy('ses_id')->limit(self::LOTE)->get();

        $ultima = null;
        foreach ($sesiones as $s) {
            if (DB::table('cont_asientos')->where('asi_clave', 'CIERRE:'.$s->ses_id)->exists()) {
                $ultima = $s->ses_fecha_cierre;

                continue;
            }
            try {
                DB::transaction(function () use ($s, &$r) {
                    if ($this->cierre($s)) {
                        $r['generados']++;
                    }
                });
                $ultima = $s->ses_fecha_cierre;
            } catch (\Throwable $e) {
                Log::warning("Contabilidad: no se pudo contabilizar el cierre de caja #{$s->ses_id}: ".$e->getMessage());
                $r['errores'][] = "Cierre de caja #{$s->ses_id}: ".$e->getMessage();
                break; // no se avanza: se reintenta en la próxima pasada
            }
        }

        if ($ultima) {
            ConfiguracionService::set(['cont_marca_cierres' => (string) $ultima]);
        }
    }

    private function cierre(object $s): bool
    {
        $fecha = Carbon::parse($s->ses_fecha_cierre);
        $apertura = Carbon::parse($s->ses_fecha_apertura ?: $s->ses_fecha_cierre);
        $debe = [];
        $haber = [];
        $lineas = [];

        foreach (['GS' => 'gs', 'USD' => 'usd', 'BRL' => 'brl'] as $moneda => $sufijo) {
            $tasa = $this->c->tasa($moneda, $fecha);
            $caja = $this->c->cuentaPorClave('caja_'.$sufijo);
            $colDif = 'ses_diferencia_'.$sufijo;

            // Faltante o sobrante.
            $dif = round((float) ($s->{$colDif} ?? 0), 2);
            $difGs = round($dif * $tasa, 2);
            if (abs($difGs) >= 0.5) {
                if ($difGs < 0) {
                    $lineas[] = [$this->c->cuentaPorClave('faltante_caja'), -$difGs, 0.0, "Faltante en caja ({$moneda})"];
                    $lineas[] = [$caja, 0.0, -$difGs, "Faltante en caja ({$moneda})"];
                } else {
                    $lineas[] = [$caja, $difGs, 0.0, "Sobrante en caja ({$moneda})"];
                    $lineas[] = [$this->c->cuentaPorClave('sobrante_caja'), 0.0, $difGs, "Sobrante en caja ({$moneda})"];
                }
            }

            if ($moneda === 'GS') {
                continue;
            }

            // Diferencia de cambio del efectivo en moneda extranjera durante la sesión.
            $esperado = (float) ($s->{'ses_esperado_'.$sufijo} ?? $s->{'ses_monto_esperado_'.$sufijo} ?? 0);
            if ($esperado == 0.0) {
                continue;
            }
            $libro = (float) $s->{'ses_monto_inicial_'.$sufijo} * $this->c->tasa($moneda, $apertura);
            $movs = DB::table('caja_movimientos')->where('ses_id', $s->ses_id)->where('mov_moneda', $moneda)
                ->whereRaw("COALESCE(mov_forma_pago, 'EFECTIVO') = 'EFECTIVO'")->get(['mov_tipo', 'mov_monto', 'mov_fecha']);
            foreach ($movs as $m) {
                $signo = $m->mov_tipo === 'INGRESO' ? 1 : -1;
                $libro += $signo * (float) $m->mov_monto * $this->c->tasa($moneda, Carbon::parse($m->mov_fecha));
            }
            $cambio = round($esperado * $tasa - $libro, 2);
            if (abs($cambio) >= 1) {
                if ($cambio > 0) {
                    $lineas[] = [$caja, $cambio, 0.0, "Revalúo del efectivo en {$moneda}"];
                    $lineas[] = [$this->c->cuentaPorClave('dif_cambio_ganancia'), 0.0, $cambio, "Diferencia de cambio {$moneda}"];
                } else {
                    $lineas[] = [$this->c->cuentaPorClave('dif_cambio_perdida'), -$cambio, 0.0, "Diferencia de cambio {$moneda}"];
                    $lineas[] = [$caja, 0.0, -$cambio, "Revalúo del efectivo en {$moneda}"];
                }
            }
        }

        if ($lineas === []) {
            return false;
        }

        $this->c->crearAsiento($fecha->toDateString(), 'Cierre de caja N° '.$s->ses_id, 'CIERRE_CAJA', 'CIERRE:'.$s->ses_id, $lineas);

        return true;
    }

    // ------------------------------------------------------------------ ajustes de stock

    private function stock(string $inicio, array &$r): void
    {
        $desde = (int) ConfiguracionService::get('cont_marca_stock', 0);

        $movs = DB::table('stock_movimientos')
            ->where('smo_id', '>', $desde)
            ->whereIn('smo_tipo', ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA', 'CONTEO', 'INGRESO_MERCADERIA', 'DEVOLUCION_PROVEEDOR'])
            ->where('smo_fecha', '<=', now()->subSeconds(30))
            ->whereDate('smo_fecha', '>=', $inicio)
            ->orderBy('smo_id')->limit(self::LOTE)->get();

        $ultimo = null;
        foreach ($movs as $m) {
            try {
                DB::transaction(function () use ($m, &$r) {
                    $ok = $m->smo_tipo === 'DEVOLUCION_PROVEEDOR' ? $this->devolucionProveedor($m) : $this->ajusteStock($m);
                    if ($ok) {
                        $r['generados']++;
                    }
                });
                $ultimo = (int) $m->smo_id;
            } catch (\Throwable $e) {
                Log::warning("Contabilidad: no se pudo contabilizar el movimiento de stock #{$m->smo_id}: ".$e->getMessage());
                $r['errores'][] = "Movimiento de stock #{$m->smo_id}: ".$e->getMessage();
                break;
            }
        }

        if ($ultimo) {
            ConfiguracionService::set(['cont_marca_stock' => (string) $ultimo]);
        }
    }

    private function ajusteStock(object $m): bool
    {
        $p = DB::table('productos')->where('pro_id', $m->pro_id)->first(['pro_nombre', 'pro_preciocosto', 'cat_id']);
        if (! $p) {
            return false;
        }

        $cant = (float) $m->smo_cantidad;
        $valor = round(abs($cant) * (float) $p->pro_preciocosto, 2);
        if ($valor <= 0) {
            return false;
        }

        $inv = $this->c->cuentaDeCategoria('CAT_INVENTARIO', $p->cat_id ? (int) $p->cat_id : null);
        $nombre = mb_substr((string) $p->pro_nombre, 0, 60);
        $motivo = $m->smo_motivo ? ' — '.mb_substr($m->smo_motivo, 0, 80) : '';
        $fecha = self::fecha($m->smo_fecha);

        if ($cant < 0) {
            $this->c->crearAsiento(
                $fecha, "Faltante de inventario: {$nombre}{$motivo}", 'AJUSTE_STOCK', 'STOCK:'.$m->smo_id,
                [[$this->c->cuentaPorClave('faltante_inventario'), $valor, 0.0, null], [$inv, 0.0, $valor, null]]
            );
        } else {
            $this->c->crearAsiento(
                $fecha, "Sobrante / ingreso de inventario: {$nombre}{$motivo}", 'AJUSTE_STOCK', 'STOCK:'.$m->smo_id,
                [[$inv, $valor, 0.0, null], [$this->c->cuentaPorClave('sobrante_inventario'), 0.0, $valor, null]]
            );
        }

        return true;
    }

    private function devolucionProveedor(object $m): bool
    {
        if (! preg_match('/^compra:(\d+)$/', (string) $m->smo_referencia, $f)) {
            return false;
        }

        $comId = (int) $f[1];
        $p = DB::table('productos')->where('pro_id', $m->pro_id)->first(['pro_tipo_iva', 'cat_id']);
        $costo = (float) DB::table('detalle_compras')->where('com_id', $comId)->where('pro_id', $m->pro_id)->value('dco_costo');
        $bruto = round(abs((float) $m->smo_cantidad) * $costo, 2);
        if (! $p || $bruto <= 0) {
            return false;
        }

        $iva = self::iva($bruto, (int) $p->pro_tipo_iva);
        $lineas = [
            [$this->c->cuentaPorClave('proveedores'), $bruto, 0.0, 'Baja de la deuda con el proveedor'],
            [$this->c->cuentaDeCategoria('CAT_INVENTARIO', $p->cat_id ? (int) $p->cat_id : null), 0.0, round($bruto - $iva, 2), 'Mercadería devuelta'],
        ];
        if ($iva > 0) {
            $lineas[] = [$this->c->cuentaPorClave('iva_credito'), 0.0, $iva, 'IVA crédito devuelto'];
        }

        $this->c->crearAsiento(self::fecha($m->smo_fecha), "Devolución al proveedor (compra N° {$comId})", 'COMPRA', 'DEVPROV:'.$m->smo_id, $lineas);

        return true;
    }
}
