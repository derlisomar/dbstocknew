<?php

namespace App\Reportes;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Tablero de inicio del Centro de Reportes: cifras clave del día, con acceso directo al reporte que las explica. */
class Tablero
{
    private static function gs($v): string
    {
        return 'Gs. '.number_format((float) $v, 0, ',', '.');
    }

    private static function var(float $actual, float $anterior): string
    {
        if ($anterior <= 0) {
            return $actual > 0 ? 'sin dato anterior' : '';
        }
        $p = ($actual - $anterior) / $anterior * 100;

        return ($p >= 0 ? '▲ ' : '▼ ').number_format(abs($p), 1, ',', '.').'%';
    }

    private static function link(string $clave, array $q = []): string
    {
        return route('reportes.ver', array_merge(['clave' => $clave], array_filter($q, fn ($v) => $v !== null)));
    }

    /** @return array{tarjetas: list<array<string,mixed>>, serie: ?array, top: ?array, hoy: string} */
    public static function datos(User $u, ?int $suc): array
    {
        $hoy = Carbon::today();
        $t = [];
        $serie = null;
        $top = null;
        $sq = ['suc_id' => $suc];

        if ($u->tienePermiso('REPORTES_VENTAS')) {
            $ventas = fn (Carbon $d, Carbon $h) => DB::table('ventas as v')->where('v.vta_estado', 'CONFIRMADA')->whereBetween('v.vta_fecha', [$d->format('Y-m-d 00:00:00'), $h->format('Y-m-d 23:59:59')])
                ->when($suc, fn ($q) => $q->where('v.suc_id', $suc));
            $h = $ventas($hoy, $hoy)->selectRaw('count(*) as n, coalesce(sum(v.vta_total),0) as t')->first();
            $ay = (float) $ventas($hoy->copy()->subDay(), $hoy->copy()->subDay())->sum('v.vta_total');
            $mes = (float) $ventas($hoy->copy()->startOfMonth(), $hoy)->sum('v.vta_total');
            $ini = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
            $fin = $ini->copy()->addDays($hoy->day - 1);
            if ($fin->gt($ini->copy()->endOfMonth())) {
                $fin = $ini->copy()->endOfMonth();
            }
            $mesAnt = (float) $ventas($ini, $fin)->sum('v.vta_total');
            $mm = $hoy->format('Y-m-01');
            $t[] = ['grupo' => 'ventas', 'titulo' => 'Ventas de hoy', 'valor' => self::gs($h->t), 'sub' => (int) $h->n.' ventas · ayer '.self::gs($ay).' '.self::var((float) $h->t, $ay), 'tono' => 'azul',
                'url' => self::link('ventas_periodo', $sq + ['desde' => $hoy->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d')])];
            $t[] = ['grupo' => 'ventas', 'titulo' => 'Ventas del mes', 'valor' => self::gs($mes), 'sub' => 'vs. mes anterior (mismo tramo) '.self::var($mes, $mesAnt), 'tono' => 'azul',
                'url' => self::link('ventas_periodo', $sq + ['desde' => $mm, 'hasta' => $hoy->format('Y-m-d')])];
            $t[] = ['grupo' => 'ventas', 'titulo' => 'Ticket promedio de hoy', 'valor' => self::gs($h->n ? $h->t / $h->n : 0), 'sub' => 'por venta confirmada', 'tono' => 'azul',
                'url' => self::link('usuario_rendimiento', $sq + ['desde' => $hoy->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d')])];

            $k = Catalogo::expr('dia', 'v.vta_fecha');
            $dias = $ventas($hoy->copy()->subDays(13), $hoy)->selectRaw("$k as k, sum(v.vta_total) as t")->groupByRaw($k)->pluck('t', 'k');
            $serie = [];
            for ($i = 13; $i >= 0; $i--) {
                $d = $hoy->copy()->subDays($i);
                $serie[] = ['dia' => $d->format('d/m'), 'sem' => $d->locale('es')->translatedFormat('D'), 'total' => (float) ($dias[$d->format('Y-m-d')] ?? 0)];
            }
            $top = $ventas($hoy->copy()->startOfMonth(), $hoy)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
                ->selectRaw('p.pro_nombre, sum(d.det_cantidad) as u, sum(d.det_subtotal) as t')->groupBy('p.pro_id', 'p.pro_nombre')->orderByDesc('t')->limit(5)->get()
                ->map(fn ($r) => ['nombre' => $r->pro_nombre, 'unidades' => (float) $r->u, 'total' => (float) $r->t])->all();
        }

        if ($u->tienePermiso('REPORTES_STOCK')) {
            $p = fn () => DB::table('productos as p')->where('p.pro_activo', true)->when($suc, fn ($q) => $q->where('p.suc_id', $suc));
            $bajo = $p()->whereColumn('p.pro_stockactual', '<=', 'p.pro_stockminimo')->count();
            $agot = $p()->where('p.pro_stockactual', '<=', 0)->count();
            $venc = $p()->whereNotNull('p.pro_fechavencimiento')->where('p.pro_fechavencimiento', '<=', $hoy->copy()->addDays(30)->format('Y-m-d'))->where('p.pro_stockactual', '>', 0)->count();
            $vencidos = $p()->whereNotNull('p.pro_fechavencimiento')->where('p.pro_fechavencimiento', '<', $hoy->format('Y-m-d'))->where('p.pro_stockactual', '>', 0)->count();
            $t[] = ['grupo' => 'stock', 'titulo' => 'Stock crítico', 'valor' => (string) $bajo, 'sub' => $agot.' agotados · para reponer', 'tono' => $bajo ? 'ambar' : 'verde', 'url' => self::link('stock_bajo_minimo', $sq)];
            $t[] = ['grupo' => 'stock', 'titulo' => 'Vencidos / por vencer (30 días)', 'valor' => (string) $venc, 'sub' => $vencidos.' ya vencidos', 'tono' => $vencidos ? 'rojo' : ($venc ? 'ambar' : 'verde'), 'url' => self::link('stock_vencimientos', $sq + ['dias' => 30])];
        }

        if ($u->tienePermiso('REPORTES_CARTERA')) {
            $c = DB::table('cuentas_cobrar as cc')->join('ventas as v', 'v.vta_id', '=', 'cc.vta_id')->where('v.vta_estado', 'CONFIRMADA')->where('cc.cred_saldo_pendiente', '>', 0)
                ->whereNotIn('cc.cred_estado', ['PAGADA', 'ANULADA'])->when($suc, fn ($q) => $q->where('v.suc_id', $suc));
            $total = (float) (clone $c)->sum('cc.cred_saldo_pendiente');
            $venc = (clone $c)->where('cc.cred_fecha_vencimiento', '<', $hoy->format('Y-m-d'));
            $vm = (float) (clone $venc)->sum('cc.cred_saldo_pendiente');
            $nc = (clone $venc)->distinct()->count('cc.cli_id');
            $t[] = ['grupo' => 'cartera', 'titulo' => 'Deuda vencida de clientes', 'valor' => self::gs($vm), 'sub' => $nc.' clientes · total por cobrar '.self::gs($total), 'tono' => $vm > 0 ? 'rojo' : 'verde', 'url' => self::link('cartera_antiguedad', $sq)];
        }

        if ($u->tienePermiso('REPORTES_COMPRAS')) {
            $c = DB::table('cuentas_pagar as cp')->join('compras as co', 'co.com_id', '=', 'cp.com_id')->where('co.com_estado', 'REGISTRADA')->where('cp.cpa_saldo_pendiente', '>', 0)
                ->whereNotIn('cp.cpa_estado', ['PAGADA', 'ANULADA'])->when($suc, fn ($q) => $q->where('co.suc_id', $suc));
            $total = (float) (clone $c)->sum('cp.cpa_saldo_pendiente');
            $vm = (float) (clone $c)->where('cp.cpa_fecha_vencimiento', '<', $hoy->format('Y-m-d'))->sum('cp.cpa_saldo_pendiente');
            $t[] = ['grupo' => 'compras', 'titulo' => 'Deuda vencida con proveedores', 'valor' => self::gs($vm), 'sub' => 'total por pagar '.self::gs($total), 'tono' => $vm > 0 ? 'rojo' : 'verde', 'url' => self::link('pagar_pendientes', $sq)];
        }

        if ($u->tienePermiso('REPORTES_CAJA')) {
            $s = fn () => DB::table('caja_sesiones as s')->join('cajas as cj', 'cj.caj_id', '=', 's.caj_id')->when($suc, fn ($q) => $q->where('cj.suc_id', $suc));
            $abiertas = $s()->where('s.ses_estado', 'ABIERTA')->count();
            $dif = (float) $s()->where('s.ses_estado', 'CERRADA')->where('s.ses_fecha_apertura', '>=', $hoy->format('Y-m-01 00:00:00'))->sum('s.ses_diferencia_gs');
            $t[] = ['grupo' => 'caja', 'titulo' => 'Diferencia de caja del mes', 'valor' => self::gs($dif), 'sub' => $abiertas.' cajas abiertas ahora', 'tono' => $dif < 0 ? 'rojo' : 'verde',
                'url' => self::link('caja_diferencias', $sq + ['desde' => $hoy->format('Y-m-01'), 'hasta' => $hoy->format('Y-m-d')])];
        }

        if ($u->tienePermiso('REPORTES_FISCAL')) {
            $rows = DB::table('sucursales as sc')->where('sc.suc_activa', true)->when($suc, fn ($q) => $q->where('sc.suc_id', $suc))->get();
            $vencidos = $rows->filter(fn ($r) => ! $r->suc_timbrado || ($r->suc_timbrado_fin && Carbon::parse($r->suc_timbrado_fin)->lt($hoy)))->count();
            $porVencer = $rows->filter(fn ($r) => $r->suc_timbrado && $r->suc_timbrado_fin && Carbon::parse($r->suc_timbrado_fin)->between($hoy, $hoy->copy()->addDays(30)))->count();
            $t[] = ['grupo' => 'fiscal', 'titulo' => 'Timbrados', 'valor' => $vencidos ? $vencidos.' vencido(s)' : ($porVencer ? $porVencer.' por vencer' : 'Al día'), 'sub' => $porVencer.' vencen en 30 días',
                'tono' => $vencidos ? 'rojo' : ($porVencer ? 'ambar' : 'verde'), 'url' => self::link('fiscal_vigencia', $sq)];
        }

        return ['tarjetas' => $t, 'serie' => $serie, 'top' => $top, 'hoy' => $hoy->locale('es')->translatedFormat('l d \d\e F \d\e Y')];
    }
}
