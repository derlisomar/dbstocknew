<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Consultas del módulo contable: diario, mayor, balances y libros de IVA. Todo en guaraníes. */
class ContabilidadInformes
{
    public function __construct(private ContabilidadService $c)
    {
    }

    /** Libro diario: asientos con sus líneas. */
    public function diario(string $desde, string $hasta, ?string $origen = null, ?string $texto = null, int $porPagina = 25)
    {
        $q = DB::table('cont_asientos')->whereBetween('asi_fecha', [$desde, $hasta]);
        if ($origen) {
            $q->where('asi_origen', $origen);
        }
        if ($texto) {
            $q->where(function ($w) use ($texto) {
                $w->where('asi_glosa', 'like', '%'.$texto.'%');
                if (ctype_digit($texto)) {
                    $w->orWhere('asi_numero', (int) $texto);
                }
            });
        }

        $pagina = $q->orderBy('asi_fecha')->orderBy('asi_numero')->paginate($porPagina)->withQueryString();

        $lineas = DB::table('cont_lineas as l')
            ->join('cont_cuentas as c', 'c.cue_id', '=', 'l.cue_id')
            ->whereIn('l.asi_id', $pagina->pluck('asi_id'))
            ->orderBy('l.lin_id')
            ->get(['l.asi_id', 'c.cue_codigo', 'c.cue_nombre', 'l.lin_debe', 'l.lin_haber', 'l.lin_detalle'])
            ->groupBy('asi_id');

        return [$pagina, $lineas];
    }

    /**
     * Libro mayor de una cuenta.
     *
     * @return array{cuenta:object, anterior:float, filas:Collection, debe:float, haber:float, saldo:float}
     */
    public function mayor(int $cueId, string $desde, string $hasta): array
    {
        $cuenta = DB::table('cont_cuentas')->where('cue_id', $cueId)->first();
        $deudora = ContabilidadService::naturalezaDeudora($cuenta->cue_tipo);

        $ant = DB::table('cont_lineas as l')->join('cont_asientos as a', 'a.asi_id', '=', 'l.asi_id')
            ->where('l.cue_id', $cueId)->where('a.asi_fecha', '<', $desde)
            ->selectRaw('COALESCE(SUM(l.lin_debe),0) as d, COALESCE(SUM(l.lin_haber),0) as h')->first();
        $anterior = round($deudora ? $ant->d - $ant->h : $ant->h - $ant->d, 2);

        $movs = DB::table('cont_lineas as l')->join('cont_asientos as a', 'a.asi_id', '=', 'l.asi_id')
            ->where('l.cue_id', $cueId)->whereBetween('a.asi_fecha', [$desde, $hasta])
            ->orderBy('a.asi_fecha')->orderBy('a.asi_numero')->orderBy('l.lin_id')
            ->get(['a.asi_id', 'a.asi_numero', 'a.asi_fecha', 'a.asi_glosa', 'l.lin_debe', 'l.lin_haber']);

        $saldo = $anterior;
        $debe = $haber = 0.0;
        $filas = $movs->map(function ($m) use (&$saldo, &$debe, &$haber, $deudora) {
            $saldo = round($saldo + ($deudora ? $m->lin_debe - $m->lin_haber : $m->lin_haber - $m->lin_debe), 2);
            $debe += (float) $m->lin_debe;
            $haber += (float) $m->lin_haber;
            $m->saldo = $saldo;

            return $m;
        });

        return compact('cuenta', 'anterior', 'filas', 'debe', 'haber', 'saldo');
    }

    /**
     * Sumas por cuenta en un período.
     *
     * @return Collection<int, object>  cue_id, cue_codigo, cue_nombre, cue_tipo, debe, haber, saldo_deudor, saldo_acreedor
     */
    public function sumas(?string $desde, string $hasta): Collection
    {
        $q = DB::table('cont_cuentas as c')
            ->leftJoin('cont_lineas as l', 'l.cue_id', '=', 'c.cue_id')
            ->leftJoin('cont_asientos as a', function ($j) use ($desde, $hasta) {
                $j->on('a.asi_id', '=', 'l.asi_id')->where('a.asi_fecha', '<=', $hasta);
                if ($desde) {
                    $j->where('a.asi_fecha', '>=', $desde);
                }
            })
            ->where('c.cue_imputable', true)
            ->groupBy('c.cue_id', 'c.cue_codigo', 'c.cue_nombre', 'c.cue_tipo')
            ->orderBy('c.cue_codigo')
            ->selectRaw('c.cue_id, c.cue_codigo, c.cue_nombre, c.cue_tipo,
                COALESCE(SUM(CASE WHEN a.asi_id IS NOT NULL THEN l.lin_debe ELSE 0 END),0) as debe,
                COALESCE(SUM(CASE WHEN a.asi_id IS NOT NULL THEN l.lin_haber ELSE 0 END),0) as haber');

        return $q->get()->map(function ($f) {
            $f->debe = round((float) $f->debe, 2);
            $f->haber = round((float) $f->haber, 2);
            $dif = round($f->debe - $f->haber, 2);
            $f->saldo_deudor = $dif > 0 ? $dif : 0.0;
            $f->saldo_acreedor = $dif < 0 ? -$dif : 0.0;

            return $f;
        })->filter(fn ($f) => $f->debe != 0.0 || $f->haber != 0.0)->values();
    }

    /** @return array{ingresos:Collection, egresos:Collection, total_ingresos:float, total_egresos:float, resultado:float} */
    public function resultados(string $desde, string $hasta): array
    {
        $s = $this->sumas($desde, $hasta);
        $ing = $s->where('cue_tipo', 'INGRESO')->map(function ($f) {
            $f->importe = round($f->haber - $f->debe, 2);

            return $f;
        })->values();
        $egr = $s->where('cue_tipo', 'EGRESO')->map(function ($f) {
            $f->importe = round($f->debe - $f->haber, 2);

            return $f;
        })->values();

        $ti = round($ing->sum('importe'), 2);
        $te = round($egr->sum('importe'), 2);

        return ['ingresos' => $ing, 'egresos' => $egr, 'total_ingresos' => $ti, 'total_egresos' => $te, 'resultado' => round($ti - $te, 2)];
    }

    /** @return array{activo:Collection, pasivo:Collection, patrimonio:Collection, total_activo:float, total_pasivo:float, total_patrimonio:float, resultado:float, cuadra:bool} */
    public function balance(string $hasta): array
    {
        $s = $this->sumas(null, $hasta);

        $deudoras = fn ($tipo) => $s->where('cue_tipo', $tipo)->map(function ($f) {
            $f->importe = round($f->debe - $f->haber, 2);

            return $f;
        })->values();
        $acreedoras = fn ($tipo) => $s->where('cue_tipo', $tipo)->map(function ($f) {
            $f->importe = round($f->haber - $f->debe, 2);

            return $f;
        })->values();

        $activo = $deudoras('ACTIVO');
        $pasivo = $acreedoras('PASIVO');
        $patrimonio = $acreedoras('PATRIMONIO');
        $resultado = round(
            $s->where('cue_tipo', 'INGRESO')->sum(fn ($f) => $f->haber - $f->debe)
            - $s->where('cue_tipo', 'EGRESO')->sum(fn ($f) => $f->debe - $f->haber),
            2
        );

        $ta = round($activo->sum('importe'), 2);
        $tp = round($pasivo->sum('importe'), 2);
        $tpat = round($patrimonio->sum('importe') + $resultado, 2);

        return [
            'activo' => $activo, 'pasivo' => $pasivo, 'patrimonio' => $patrimonio,
            'total_activo' => $ta, 'total_pasivo' => $tp, 'total_patrimonio' => $tpat, 'resultado' => $resultado,
            'cuadra' => abs($ta - ($tp + $tpat)) < 0.05,
        ];
    }

    // ------------------------------------------------------------------ libros de IVA

    /** @return list<array<string,mixed>> */
    public function libroIvaVentas(string $desde, string $hasta): array
    {
        $ventas = DB::table('ventas as v')
            ->leftJoin('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->whereDate('v.vta_fecha', '>=', $desde)->whereDate('v.vta_fecha', '<=', $hasta)
            ->orderBy('v.vta_fecha')->orderBy('v.vta_id')
            ->get(['v.vta_id', 'v.vta_fecha', 'v.vta_tipo', 'v.vta_total', 'v.vta_estado', 'v.vta_timbrado', 'v.vta_nro_factura',
                'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci']);

        $det = DB::table('detalle_ventas as d')->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->whereIn('d.vta_id', $ventas->pluck('vta_id'))
            ->get(['d.vta_id', 'd.det_subtotal', 'p.pro_tipo_iva'])->groupBy('vta_id');

        $filas = [];
        foreach ($ventas as $v) {
            $anulada = $v->vta_estado === 'ANULADA';
            [$g10, $g5, $ex] = $this->brutos($det->get($v->vta_id, collect()), 'det_subtotal');
            $filas[] = $this->filaIva(
                $v->vta_fecha, $anulada ? 'Anulada' : ($v->vta_tipo === 'CREDITO' ? 'Factura crédito' : 'Factura contado'),
                $v->vta_timbrado, $v->vta_nro_factura ?: ('Venta '.$v->vta_id),
                $v->cli_ruc_ci, trim(($v->cli_nombre ?? '').' '.($v->cli_apellido ?? '')),
                $anulada ? [0, 0, 0] : [$g10, $g5, $ex]
            );
        }

        // Devoluciones: restan (equivalen a una nota de crédito interna).
        $devs = DB::table('devoluciones as dv')
            ->join('ventas as v', 'v.vta_id', '=', 'dv.vta_id')
            ->leftJoin('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->whereDate('dv.dev_fecha', '>=', $desde)->whereDate('dv.dev_fecha', '<=', $hasta)->where('v.vta_estado', 'CONFIRMADA')
            ->orderBy('dv.dev_fecha')->get(['dv.dev_id', 'dv.dev_fecha', 'v.vta_timbrado', 'v.vta_nro_factura', 'v.vta_id', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci']);
        $ddet = DB::table('detalle_devoluciones as dd')->join('productos as p', 'p.pro_id', '=', 'dd.pro_id')
            ->whereIn('dd.dev_id', $devs->pluck('dev_id'))->get(['dd.dev_id', 'dd.ddv_subtotal', 'p.pro_tipo_iva'])->groupBy('dev_id');
        foreach ($devs as $d) {
            [$g10, $g5, $ex] = $this->brutos($ddet->get($d->dev_id, collect()), 'ddv_subtotal');
            $filas[] = $this->filaIva(
                $d->dev_fecha, 'Devolución', $d->vta_timbrado, 'Dev. '.$d->dev_id.' (fact. '.($d->vta_nro_factura ?: $d->vta_id).')',
                $d->cli_ruc_ci, trim(($d->cli_nombre ?? '').' '.($d->cli_apellido ?? '')), [-$g10, -$g5, -$ex]
            );
        }

        usort($filas, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        return $filas;
    }

    /** @return list<array<string,mixed>> */
    public function libroIvaCompras(string $desde, string $hasta): array
    {
        $compras = DB::table('compras as c')
            ->leftJoin('proveedores as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.com_estado', 'REGISTRADA')
            ->whereDate('c.com_fecha', '>=', $desde)->whereDate('c.com_fecha', '<=', $hasta)
            ->orderBy('c.com_fecha')->orderBy('c.com_id')
            ->get(['c.com_id', 'c.com_fecha', 'c.com_tipo', 'c.com_nro_documento', 'c.com_timbrado', 'p.prov_razonsocial', 'p.prov_ruc']);

        $det = DB::table('detalle_compras as d')->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->whereIn('d.com_id', $compras->pluck('com_id'))->get(['d.com_id', 'd.dco_subtotal', 'p.pro_tipo_iva'])->groupBy('com_id');

        $filas = [];
        foreach ($compras as $c) {
            [$g10, $g5, $ex] = $this->brutos($det->get($c->com_id, collect()), 'dco_subtotal');
            $filas[] = $this->filaIva(
                $c->com_fecha, $c->com_tipo === 'CREDITO' ? 'Factura crédito' : 'Factura contado', $c->com_timbrado,
                $c->com_nro_documento ?: ('Compra '.$c->com_id), $c->prov_ruc, (string) $c->prov_razonsocial, [$g10, $g5, $ex]
            );
        }

        return $filas;
    }

    /** @return array{0:float,1:float,2:float} brutos (IVA incluido) al 10 %, al 5 % y exentos */
    private function brutos(Collection $lineas, string $col): array
    {
        $g10 = $g5 = $ex = 0.0;
        foreach ($lineas as $l) {
            $v = (float) $l->{$col};
            match ((int) $l->pro_tipo_iva) {
                10 => $g10 += $v,
                5 => $g5 += $v,
                default => $ex += $v,
            };
        }

        return [round($g10, 2), round($g5, 2), round($ex, 2)];
    }

    /** @param array{0:float,1:float,2:float} $b */
    private function filaIva($fecha, string $tipo, ?string $timbrado, string $nro, ?string $ruc, string $nombre, array $b): array
    {
        [$g10, $g5, $ex] = $b;
        $iva10 = round($g10 / 11, 2);
        $iva5 = round($g5 / 21, 2);

        return [
            'fecha' => Carbon::parse($fecha)->toDateString(),
            'tipo' => $tipo,
            'timbrado' => (string) $timbrado,
            'numero' => $nro,
            'ruc' => (string) $ruc,
            'nombre' => $nombre,
            'base10' => round($g10 - $iva10, 2),
            'iva10' => $iva10,
            'base5' => round($g5 - $iva5, 2),
            'iva5' => $iva5,
            'exento' => $ex,
            'total' => round($g10 + $g5 + $ex, 2),
        ];
    }

    /** Valor del inventario según el sistema (para armar el asiento de apertura). */
    public function valorInventario(): float
    {
        return round((float) DB::table('productos')->where('pro_stockactual', '>', 0)->sum(DB::raw('pro_stockactual * pro_preciocosto')), 2);
    }
}
