<?php

namespace App\Reportes;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de reportes del Centro de Reportes.
 * Cada reporte devuelve ['columnas' => [...], 'filas' => [...]] para UNA sucursal
 * (o para todo el negocio si $suc es null). El resto —pantalla, Excel, PDF,
 * una hoja por sucursal— lo resuelve ReporteController con el Exportador.
 */
class Catalogo
{
    public const GRUPOS = [
        'ventas' => ['nombre' => 'Ventas', 'icono' => '🛒', 'permiso' => 'REPORTES_VENTAS', 'color' => 'blue'],
        'stock' => ['nombre' => 'Stock e inventario', 'icono' => '📦', 'permiso' => 'REPORTES_STOCK', 'color' => 'emerald'],
        'cartera' => ['nombre' => 'Clientes y cartera', 'icono' => '👥', 'permiso' => 'REPORTES_CARTERA', 'color' => 'amber'],
        'compras' => ['nombre' => 'Compras y proveedores', 'icono' => '🚚', 'permiso' => 'REPORTES_COMPRAS', 'color' => 'violet'],
        'caja' => ['nombre' => 'Caja y finanzas', 'icono' => '💰', 'permiso' => 'REPORTES_CAJA', 'color' => 'rose'],
        'fiscal' => ['nombre' => 'Fiscal e impuestos', 'icono' => '🧾', 'permiso' => 'REPORTES_FISCAL', 'color' => 'cyan'],
    ];

    /**
     * filtros posibles: fechas, agrupar(opciones), dias(etiqueta,default), producto, cliente, usuario
     * costos: el reporte muestra columnas de costo/utilidad (se ocultan sin REPORTES_COSTOS)
     * global: no depende de la sucursal (se muestra una sola vez)
     */
    public static function todos(): array
    {
        return [
            // ------------------------------------------------------------- VENTAS
            'ventas_periodo' => ['grupo' => 'ventas', 'nombre' => 'Ventas por período', 'desc' => 'Total vendido por día o por mes, con variación contra el período anterior, contado vs. crédito y ticket promedio.', 'filtros' => ['fechas', 'agrupar' => ['dia' => 'Por día', 'mes' => 'Por mes']]],
            'ventas_usuario' => ['grupo' => 'ventas', 'nombre' => 'Ventas por cajero / vendedor', 'desc' => 'Cuánto vendió cada usuario, cantidad de operaciones, ticket promedio y anulaciones.', 'filtros' => ['fechas']],
            'ventas_formapago' => ['grupo' => 'ventas', 'nombre' => 'Ventas por forma de pago', 'desc' => 'Efectivo, tarjeta, transferencia, crédito: cuánto entró por cada medio.', 'filtros' => ['fechas']],
            'ventas_categoria' => ['grupo' => 'ventas', 'nombre' => 'Ventas por categoría', 'desc' => 'Unidades e ingresos por categoría. Con permiso de costos: utilidad y margen.', 'filtros' => ['fechas'], 'costos' => true],
            'ventas_producto' => ['grupo' => 'ventas', 'nombre' => 'Productos más vendidos', 'desc' => 'Ranking de los 200 productos que más ingresos dejaron. Con permiso de costos: utilidad y margen.', 'filtros' => ['fechas'], 'costos' => true],
            'ventas_horas' => ['grupo' => 'ventas', 'nombre' => 'Horas pico', 'desc' => 'En qué horas del día se vende más (para organizar turnos y personal).', 'filtros' => ['fechas']],
            'ventas_diasemana' => ['grupo' => 'ventas', 'nombre' => 'Días de la semana', 'desc' => 'Qué días de la semana se vende más.', 'filtros' => ['fechas']],
            'usuario_rendimiento' => ['grupo' => 'ventas', 'nombre' => 'Rendimiento por cajero / vendedor', 'desc' => 'Ventas, ticket promedio, unidades por venta, anulaciones, devoluciones, cobranzas y diferencias de caja de cada usuario en un solo cuadro.', 'filtros' => ['fechas']],
            'usuario_dia' => ['grupo' => 'ventas', 'nombre' => 'Ventas por usuario y por día', 'desc' => 'Qué vendió cada cajero o vendedor cada día. Podés filtrar un usuario puntual.', 'filtros' => ['fechas', 'usuario']],
            'usuario_formapago' => ['grupo' => 'ventas', 'nombre' => 'Ventas por usuario y forma de pago', 'desc' => 'Cuánto cobró cada usuario en efectivo, tarjeta, transferencia o crédito.', 'filtros' => ['fechas', 'usuario']],
            'usuario_productos' => ['grupo' => 'ventas', 'nombre' => 'Qué vende cada usuario', 'desc' => 'Productos vendidos por cada cajero o vendedor (hasta 300 filas). Útil para comisiones y metas.', 'filtros' => ['fechas', 'usuario']],
            'ventas_listado' => ['grupo' => 'ventas', 'nombre' => 'Listado de comprobantes', 'desc' => 'Cada venta con cliente, vendedor, forma de pago y desglose de IVA.', 'filtros' => ['fechas']],
            'ventas_anuladas' => ['grupo' => 'ventas', 'nombre' => 'Ventas anuladas', 'desc' => 'Quién anuló qué, cuándo y por qué motivo. Control interno.', 'filtros' => ['fechas']],
            'ventas_devoluciones' => ['grupo' => 'ventas', 'nombre' => 'Devoluciones de clientes', 'desc' => 'Devoluciones registradas con su motivo y el usuario que las procesó.', 'filtros' => ['fechas']],

            // ------------------------------------------------------------- STOCK
            'stock_valorizado' => ['grupo' => 'stock', 'nombre' => 'Stock valorizado', 'desc' => 'Cuánto vale la mercadería hoy, por categoría o por depósito, a precio de venta y (con permiso) a costo.', 'filtros' => ['agrupar' => ['categoria' => 'Por categoría', 'deposito' => 'Por depósito']], 'costos' => true],
            'stock_bajo_minimo' => ['grupo' => 'stock', 'nombre' => 'Productos bajo el mínimo (reposición)', 'desc' => 'Lo que hay que comprar: stock actual, mínimo, cantidad sugerida y proveedor habitual.', 'filtros' => [], 'costos' => true],
            'stock_rotacion' => ['grupo' => 'stock', 'nombre' => 'Rotación y cobertura', 'desc' => 'Cuánto se vendió en el período y para cuántos días alcanza el stock actual.', 'filtros' => ['fechas']],
            'stock_sin_movimiento' => ['grupo' => 'stock', 'nombre' => 'Stock sin ventas (inmovilizado)', 'desc' => 'Productos con stock que no se venden hace N días: capital parado.', 'filtros' => ['dias' => ['Días sin vender', 60]], 'costos' => true],
            'stock_vencimientos' => ['grupo' => 'stock', 'nombre' => 'Próximos a vencer', 'desc' => 'Productos vencidos o que vencen en los próximos N días.', 'filtros' => ['dias' => ['Vencen en los próximos (días)', 30]], 'costos' => true],
            'stock_kardex' => ['grupo' => 'stock', 'nombre' => 'Kardex (movimientos de stock)', 'desc' => 'Entradas, salidas y ajustes de un producto (código o nombre) con saldo resultante.', 'filtros' => ['fechas', 'producto']],
            'stock_ajustes' => ['grupo' => 'stock', 'nombre' => 'Ajustes y conteos', 'desc' => 'Diferencias de inventario: quién ajustó, cuánto y por qué.', 'filtros' => ['fechas'], 'costos' => true],

            // ------------------------------------------------------------- CARTERA
            'cartera_antiguedad' => ['grupo' => 'cartera', 'nombre' => 'Cuentas por cobrar (antigüedad)', 'desc' => 'Deuda de cada cliente separada en al día, 1-30, 31-60, 61-90 y más de 90 días de atraso.', 'filtros' => []],
            'cartera_pendientes' => ['grupo' => 'cartera', 'nombre' => 'Estado de cuenta (facturas pendientes)', 'desc' => 'Cada crédito pendiente con vencimiento y días de atraso. Filtrá por cliente para imprimir su estado de cuenta.', 'filtros' => ['cliente']],
            'cartera_cobranzas' => ['grupo' => 'cartera', 'nombre' => 'Cobranzas realizadas', 'desc' => 'Cobros recibidos en el período, por cliente, forma de pago y cobrador.', 'filtros' => ['fechas']],
            'cartera_top_clientes' => ['grupo' => 'cartera', 'nombre' => 'Mejores clientes', 'desc' => 'Quiénes más compraron en el período, ticket promedio y última compra.', 'filtros' => ['fechas']],
            'cartera_inactivos' => ['grupo' => 'cartera', 'nombre' => 'Clientes inactivos', 'desc' => 'Clientes que compraron antes pero no vuelven hace N días (para recuperarlos).', 'filtros' => ['dias' => ['Sin comprar hace (días)', 90]]],
            // ------------------------------------------------------------- COMPRAS
            'compras_periodo' => ['grupo' => 'compras', 'nombre' => 'Compras por período', 'desc' => 'Cuánto se compró por día o por mes, contado vs. crédito y anulaciones.', 'filtros' => ['fechas', 'agrupar' => ['dia' => 'Por día', 'mes' => 'Por mes']]],
            'compras_proveedor' => ['grupo' => 'compras', 'nombre' => 'Compras por proveedor', 'desc' => 'A quién le comprás más, cuánto y cuándo fue la última compra.', 'filtros' => ['fechas']],
            'compras_listado' => ['grupo' => 'compras', 'nombre' => 'Listado de compras', 'desc' => 'Cada compra con su comprobante, timbrado, proveedor y estado.', 'filtros' => ['fechas']],
            'compras_producto' => ['grupo' => 'compras', 'nombre' => 'Productos comprados', 'desc' => 'Qué productos se compraron, cantidades y costo promedio pagado.', 'filtros' => ['fechas']],
            'compras_costos' => ['grupo' => 'compras', 'nombre' => 'Evolución del costo', 'desc' => 'Cuánto subió o bajó el costo de cada producto entre la primera y la última compra del período.', 'filtros' => ['fechas']],
            'compras_anuladas' => ['grupo' => 'compras', 'nombre' => 'Compras anuladas', 'desc' => 'Compras anuladas con quién las anuló y el motivo.', 'filtros' => ['fechas']],
            'pagar_antiguedad' => ['grupo' => 'compras', 'nombre' => 'Cuentas por pagar (antigüedad)', 'desc' => 'Lo que debés a cada proveedor, separado por atraso: al día, 1-30, 31-60, 61-90 y más de 90 días.', 'filtros' => []],
            'pagar_pendientes' => ['grupo' => 'compras', 'nombre' => 'Facturas pendientes de pago', 'desc' => 'Cada deuda con proveedores con su vencimiento y días de atraso (para programar pagos).', 'filtros' => []],
            'pagos_proveedores' => ['grupo' => 'compras', 'nombre' => 'Pagos a proveedores', 'desc' => 'Pagos realizados en el período, por proveedor y forma de pago.', 'filtros' => ['fechas']],

            // ------------------------------------------------------------- CAJA
            'caja_sesiones' => ['grupo' => 'caja', 'nombre' => 'Aperturas y cierres de caja', 'desc' => 'Cada turno de caja: quién la abrió, monto inicial, lo esperado, lo contado y la diferencia.', 'filtros' => ['fechas']],
            'caja_diferencias' => ['grupo' => 'caja', 'nombre' => 'Faltantes y sobrantes de caja', 'desc' => 'Solo los cierres con diferencia, por cajero. Control de efectivo.', 'filtros' => ['fechas']],
            'caja_flujo' => ['grupo' => 'caja', 'nombre' => 'Flujo de caja diario (Gs.)', 'desc' => 'Ingresos, egresos y neto por día en guaraníes.', 'filtros' => ['fechas']],
            'caja_formapago' => ['grupo' => 'caja', 'nombre' => 'Movimientos por forma de pago (Gs.)', 'desc' => 'Cuánto entró y salió por efectivo, tarjeta, transferencia, etc.', 'filtros' => ['fechas']],
            'caja_extras' => ['grupo' => 'caja', 'nombre' => 'Ingresos y egresos extra', 'desc' => 'Dinero que entró o salió de caja fuera de las ventas, con su concepto y quién lo registró.', 'filtros' => ['fechas']],

            // ------------------------------------------------------------- FISCAL
            'fiscal_libro_ventas' => ['grupo' => 'fiscal', 'nombre' => 'Libro IVA ventas', 'desc' => 'Cada factura con base gravada e IVA al 10% y 5%, exentas y total. Incluye anuladas (en cero) y devoluciones (restan).', 'filtros' => ['fechas']],
            'fiscal_libro_compras' => ['grupo' => 'fiscal', 'nombre' => 'Libro IVA compras', 'desc' => 'Facturas de compra con crédito fiscal. El IVA se calcula según el tipo de IVA de cada producto comprado.', 'filtros' => ['fechas']],
            'fiscal_resumen_iva' => ['grupo' => 'fiscal', 'nombre' => 'Resumen de IVA por mes', 'desc' => 'Débito fiscal (ventas), crédito fiscal (compras) y saldo a pagar o a favor, mes por mes.', 'filtros' => ['fechas']],
            'fiscal_ventas_ruc' => ['grupo' => 'fiscal', 'nombre' => 'Ventas por RUC / CI del cliente', 'desc' => 'Total facturado a cada cliente con su IVA. Sirve para informar a la DNIT y revisar clientes grandes.', 'filtros' => ['fechas']],
            'fiscal_compras_ruc' => ['grupo' => 'fiscal', 'nombre' => 'Compras por RUC del proveedor', 'desc' => 'Total comprado a cada proveedor con el crédito fiscal estimado.', 'filtros' => ['fechas']],
            'fiscal_timbrados' => ['grupo' => 'fiscal', 'nombre' => 'Facturas emitidas por timbrado', 'desc' => 'Por timbrado y establecimiento: primer y último número, cantidad emitida, anuladas y números faltantes.', 'filtros' => ['fechas']],
            'fiscal_huecos' => ['grupo' => 'fiscal', 'nombre' => 'Numeración con saltos', 'desc' => 'Rangos de números de factura que faltan entre el primero y el último emitido. Control antes de una fiscalización.', 'filtros' => ['fechas']],
            'fiscal_vigencia' => ['grupo' => 'fiscal', 'nombre' => 'Vigencia de timbrados', 'desc' => 'Timbrado de cada sucursal: vigencia, días que le quedan y último número usado.', 'filtros' => []],

            'cartera_limites' => ['grupo' => 'cartera', 'nombre' => 'Límites de crédito', 'desc' => 'Clientes con crédito: límite, deuda actual, disponible, excedidos y bloqueados.', 'filtros' => [], 'global' => true],
        ];
    }

    // ====================================================================== utilidades

    private static function c(string $t, string $tipo = 'texto', bool $total = false, bool $costo = false): array
    {
        return ['t' => $t, 'tipo' => $tipo, 'total' => $total, 'costo' => $costo];
    }

    private static function rango(array $f): array
    {
        return [$f['desde'].' 00:00:00', $f['hasta'].' 23:59:59'];
    }

    public static function expr(string $que, string $col): string
    {
        $pg = DB::connection()->getDriverName() === 'pgsql';

        return match ($que) {
            'dia' => $pg ? "to_char($col,'YYYY-MM-DD')" : "strftime('%Y-%m-%d',$col)",
            'mes' => $pg ? "to_char($col,'YYYY-MM')" : "strftime('%Y-%m',$col)",
            'hora' => $pg ? "to_char($col,'HH24')" : "strftime('%H',$col)",
            'dow' => $pg ? "extract(dow from $col)::int" : "cast(strftime('%w',$col) as integer)",
        };
    }

    private static function fecha($v, bool $hora = false): string
    {
        return $v ? Carbon::parse($v)->format($hora ? 'd/m/Y H:i' : 'd/m/Y') : '';
    }

    private static function nombre($n, $a = null): string
    {
        return trim(($n ?? '').' '.($a ?? ''));
    }

    private static function ventas(array $f, ?int $suc, bool $anuladas = false): Builder
    {
        [$d, $h] = self::rango($f);

        return DB::table('ventas as v')
            ->where('v.vta_estado', $anuladas ? 'ANULADA' : 'CONFIRMADA')
            ->whereBetween('v.vta_fecha', [$d, $h])
            ->when($suc, fn ($q) => $q->where('v.suc_id', $suc));
    }

    private static function pct($a, $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }

    /** Quita las columnas de costo si el usuario no tiene permiso. */
    public static function sinCostos(array $r): array
    {
        $quitar = [];
        foreach ($r['columnas'] as $i => $c) {
            if (! empty($c['costo'])) {
                $quitar[] = $i;
            }
        }
        if (! $quitar) {
            return $r;
        }
        $r['columnas'] = array_values(array_diff_key($r['columnas'], array_flip($quitar)));
        $r['filas'] = array_map(fn ($f) => array_values(array_diff_key($f, array_flip($quitar))), $r['filas']);

        return $r;
    }

    public static function ejecutar(string $clave, array $f, ?int $suc): array
    {
        if (! isset(self::todos()[$clave])) {
            abort(404);
        }

        return self::{'r_'.$clave}($f, $suc);
    }

    // ====================================================================== VENTAS

    private static function r_ventas_periodo(array $f, ?int $suc): array
    {
        $porMes = ($f['agrupar'] ?? 'dia') === 'mes';
        $k = self::expr($porMes ? 'mes' : 'dia', 'v.vta_fecha');
        $rows = self::ventas($f, $suc)
            ->selectRaw("$k as k, count(*) as n, sum(v.vta_total) as total,
                sum(case when v.vta_tipo = 'CONTADO' then v.vta_total else 0 end) as contado,
                sum(case when v.vta_tipo = 'CREDITO' then v.vta_total else 0 end) as credito")
            ->groupByRaw($k)->orderByRaw($k)->get();

        $anul = self::ventas($f, $suc, true)->selectRaw("$k as k, count(*) as n")->groupByRaw($k)->pluck('n', 'k');

        $filas = [];
        $prev = null;
        foreach ($rows as $r) {
            $var = ($prev !== null && $prev > 0) ? round(($r->total - $prev) / $prev * 100, 1) : null;
            $filas[] = [
                $porMes ? Carbon::createFromFormat('Y-m', $r->k)->locale('es')->translatedFormat('F Y') : Carbon::parse($r->k)->locale('es')->translatedFormat('D d/m/Y'),
                (int) $r->n, round($r->total), round($r->contado), round($r->credito),
                $r->n ? round($r->total / $r->n) : 0, $var, (int) ($anul[$r->k] ?? 0),
            ];
            $prev = (float) $r->total;
        }

        return ['columnas' => [
            self::c($porMes ? 'Mes' : 'Día'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true),
            self::c('Contado', 'dinero', true), self::c('Crédito', 'dinero', true), self::c('Ticket promedio', 'dinero'),
            self::c('Var. % vs anterior', 'porc'), self::c('Anuladas', 'entero', true),
        ], 'filas' => $filas];
    }

    private static function r_ventas_usuario(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->join('usuarios as u', 'u.usu_id', '=', 'v.usu_id')
            ->selectRaw('u.usu_id, u.usu_nombre, u.usu_apellido, count(*) as n, sum(v.vta_total) as total')
            ->groupBy('u.usu_id', 'u.usu_nombre', 'u.usu_apellido')->orderByDesc('total')->get();
        $anul = self::ventas($f, $suc, true)->selectRaw('v.usu_id, count(*) as n')->groupBy('v.usu_id')->pluck('n', 'usu_id');
        $suma = $rows->sum('total');
        $filas = [];
        foreach ($rows as $r) {
            $filas[] = [self::nombre($r->usu_nombre, $r->usu_apellido), (int) $r->n, round($r->total),
                round($r->total / max($r->n, 1)), self::pct($r->total, $suma), (int) ($anul[$r->usu_id] ?? 0)];
        }

        return ['columnas' => [self::c('Usuario'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true),
            self::c('Ticket promedio', 'dinero'), self::c('% del total', 'porc'), self::c('Anuló', 'entero', true)], 'filas' => $filas];
    }

    private static function r_ventas_formapago(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->selectRaw("coalesce(v.vta_formapago,'—') as fp, v.vta_tipo, count(*) as n, sum(v.vta_total) as total")
            ->groupBy('v.vta_formapago', 'v.vta_tipo')->orderByDesc('total')->get();
        $suma = $rows->sum('total');

        return ['columnas' => [self::c('Forma de pago'), self::c('Tipo'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('% del total', 'porc')],
            'filas' => $rows->map(fn ($r) => [$r->fp, $r->vta_tipo, (int) $r->n, round($r->total), self::pct($r->total, $suma)])->all()];
    }

    private static function r_ventas_categoria(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')
            ->join('productos as p', 'p.pro_id', '=', 'd.pro_id')->join('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->selectRaw('c.cat_nombre, sum(d.det_cantidad) as u, sum(d.det_subtotal) as ing, sum(d.det_cantidad * d.det_preciocosto) as cos')
            ->groupBy('c.cat_id', 'c.cat_nombre')->orderByDesc('ing')->get();
        $suma = $rows->sum('ing');

        return ['columnas' => [self::c('Categoría'), self::c('Unidades', 'cantidad', true), self::c('Ingreso Gs.', 'dinero', true), self::c('% del total', 'porc'),
            self::c('Costo Gs.', 'dinero', true, true), self::c('Utilidad Gs.', 'dinero', true, true), self::c('Margen %', 'porc', false, true)],
            'filas' => $rows->map(fn ($r) => [$r->cat_nombre, (float) $r->u, round($r->ing), self::pct($r->ing, $suma), round($r->cos), round($r->ing - $r->cos), self::pct($r->ing - $r->cos, $r->ing)])->all()];
    }

    private static function r_ventas_producto(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')
            ->join('productos as p', 'p.pro_id', '=', 'd.pro_id')->join('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->selectRaw('p.pro_codigo, p.pro_nombre, c.cat_nombre, sum(d.det_cantidad) as u, sum(d.det_subtotal) as ing, sum(d.det_cantidad * d.det_preciocosto) as cos')
            ->groupBy('p.pro_id', 'p.pro_codigo', 'p.pro_nombre', 'c.cat_nombre')->orderByDesc('ing')->limit(200)->get();

        return ['columnas' => [self::c('#', 'entero'), self::c('Código'), self::c('Producto'), self::c('Categoría'), self::c('Unidades', 'cantidad', true), self::c('Ingreso Gs.', 'dinero', true),
            self::c('Costo Gs.', 'dinero', true, true), self::c('Utilidad Gs.', 'dinero', true, true), self::c('Margen %', 'porc', false, true)],
            'filas' => $rows->values()->map(fn ($r, $i) => [$i + 1, $r->pro_codigo, $r->pro_nombre, $r->cat_nombre, (float) $r->u, round($r->ing), round($r->cos), round($r->ing - $r->cos), self::pct($r->ing - $r->cos, $r->ing)])->all()];
    }

    private static function r_ventas_horas(array $f, ?int $suc): array
    {
        $k = self::expr('hora', 'v.vta_fecha');
        $rows = self::ventas($f, $suc)->selectRaw("$k as h, count(*) as n, sum(v.vta_total) as total")->groupByRaw($k)->orderByRaw($k)->get();
        $suma = $rows->sum('total');

        return ['columnas' => [self::c('Franja horaria'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('Ticket promedio', 'dinero'), self::c('% del total', 'porc')],
            'filas' => $rows->map(fn ($r) => [sprintf('%02d:00 a %02d:59', $r->h, $r->h), (int) $r->n, round($r->total), round($r->total / max($r->n, 1)), self::pct($r->total, $suma)])->all()];
    }

    private static function r_ventas_diasemana(array $f, ?int $suc): array
    {
        $k = self::expr('dow', 'v.vta_fecha');
        $rows = self::ventas($f, $suc)->selectRaw("$k as d, count(*) as n, sum(v.vta_total) as total")->groupByRaw($k)->get()->keyBy('d');
        $suma = $rows->sum('total');
        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
        $filas = [];
        foreach ($dias as $n => $nom) {
            $r = $rows[$n] ?? null;
            $filas[] = [$nom, (int) ($r->n ?? 0), round($r->total ?? 0), $r ? round($r->total / max($r->n, 1)) : 0, self::pct($r->total ?? 0, $suma)];
        }

        return ['columnas' => [self::c('Día'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('Ticket promedio', 'dinero'), self::c('% del total', 'porc')], 'filas' => $filas];
    }

    private static function r_ventas_listado(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->join('clientes as c', 'c.cli_id', '=', 'v.cli_id')->join('usuarios as u', 'u.usu_id', '=', 'v.usu_id')
            ->select('v.*', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci', 'u.usu_nombre')->orderBy('v.vta_fecha')->limit(20000)->get();

        return ['columnas' => [self::c('Fecha'), self::c('Comprobante'), self::c('Cliente'), self::c('RUC/CI'), self::c('Vendedor'), self::c('Tipo'), self::c('Forma de pago'),
            self::c('Exentas', 'dinero', true), self::c('IVA 5%', 'dinero', true), self::c('IVA 10%', 'dinero', true), self::c('Total Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->vta_fecha, true), $r->vta_nro_factura ?: '#'.$r->vta_id, self::nombre($r->cli_nombre, $r->cli_apellido), $r->cli_ruc_ci, $r->usu_nombre,
                $r->vta_tipo, $r->vta_formapago, round($r->vta_total_exenta), round($r->vta_total_iva5), round($r->vta_total_iva10), round($r->vta_total)])->all()];
    }

    private static function r_ventas_anuladas(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc, true)->join('clientes as c', 'c.cli_id', '=', 'v.cli_id')->join('usuarios as u', 'u.usu_id', '=', 'v.usu_id')
            ->leftJoin('usuarios as a', 'a.usu_id', '=', 'v.vta_anulada_por')
            ->select('v.*', 'c.cli_nombre', 'c.cli_apellido', 'u.usu_nombre as vendedor', 'a.usu_nombre as anulo')->orderBy('v.vta_fecha')->get();

        return ['columnas' => [self::c('Fecha venta'), self::c('Comprobante'), self::c('Cliente'), self::c('Vendedor'), self::c('Total Gs.', 'dinero', true), self::c('Anulada por'), self::c('Fecha anulación'), self::c('Motivo')],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->vta_fecha, true), $r->vta_nro_factura ?: '#'.$r->vta_id, self::nombre($r->cli_nombre, $r->cli_apellido), $r->vendedor, round($r->vta_total), $r->anulo, self::fecha($r->vta_anulada_fecha, true), $r->vta_motivo_anulacion])->all()];
    }

    private static function r_ventas_devoluciones(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('devoluciones as dv')->join('ventas as v', 'v.vta_id', '=', 'dv.vta_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 'dv.usu_id')
            ->join('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->whereBetween('dv.dev_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('v.suc_id', $suc))
            ->select('dv.*', 'v.vta_nro_factura', 'c.cli_nombre', 'c.cli_apellido', 'u.usu_nombre')->orderBy('dv.dev_fecha')->get();

        return ['columnas' => [self::c('Fecha'), self::c('Venta'), self::c('Cliente'), self::c('Procesó'), self::c('Total devuelto Gs.', 'dinero', true), self::c('Motivo')],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->dev_fecha, true), $r->vta_nro_factura ?: '#'.$r->vta_id, self::nombre($r->cli_nombre, $r->cli_apellido), $r->usu_nombre, round($r->dev_total), $r->dev_motivo])->all()];
    }

    // ====================================================================== STOCK

    private static function productos(?int $suc): Builder
    {
        return DB::table('productos as p')->join('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->where('p.pro_activo', true)->when($suc, fn ($q) => $q->where('p.suc_id', $suc));
    }

    private static function r_stock_valorizado(array $f, ?int $suc): array
    {
        $porDep = ($f['agrupar'] ?? 'categoria') === 'deposito';
        $q = self::productos($suc);
        if ($porDep) {
            $q->join('depositos as dp', 'dp.dep_id', '=', 'p.dep_id')->selectRaw('dp.dep_nombre as g');
            $q->groupBy('dp.dep_id', 'dp.dep_nombre');
        } else {
            $q->selectRaw('c.cat_nombre as g')->groupBy('c.cat_id', 'c.cat_nombre');
        }
        $rows = $q->selectRaw('count(*) as n, sum(p.pro_stockactual) as u, sum(p.pro_stockactual * p.pro_preciocosto) as costo, sum(p.pro_stockactual * p.pro_precioventa) as venta')->orderByDesc('venta')->get();

        return ['columnas' => [self::c($porDep ? 'Depósito' : 'Categoría'), self::c('Productos', 'entero', true), self::c('Unidades', 'cantidad', true), self::c('Valor a costo Gs.', 'dinero', true, true),
            self::c('Valor a venta Gs.', 'dinero', true), self::c('Utilidad potencial Gs.', 'dinero', true, true), self::c('Margen %', 'porc', false, true)],
            'filas' => $rows->map(fn ($r) => [$r->g, (int) $r->n, (float) $r->u, round($r->costo), round($r->venta), round($r->venta - $r->costo), self::pct($r->venta - $r->costo, $r->venta)])->all()];
    }

    private static function r_stock_bajo_minimo(array $f, ?int $suc): array
    {
        $rows = self::productos($suc)->join('depositos as dp', 'dp.dep_id', '=', 'p.dep_id')->leftJoin('proveedores as pv', 'pv.prov_id', '=', 'p.prov_id')
            ->whereColumn('p.pro_stockactual', '<=', 'p.pro_stockminimo')
            ->select('p.*', 'c.cat_nombre', 'dp.dep_nombre', 'pv.prov_razonsocial')->orderBy('pv.prov_razonsocial')->orderBy('p.pro_nombre')->get();

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Categoría'), self::c('Depósito'), self::c('Proveedor'), self::c('Stock', 'cantidad'), self::c('Mínimo', 'cantidad'),
            self::c('Sugerido a comprar', 'cantidad', true), self::c('Costo estimado Gs.', 'dinero', true, true)],
            'filas' => $rows->map(function ($r) {
                $sug = max(0, ($r->pro_stockminimo * 2) - $r->pro_stockactual);
                if ($r->pro_unidad === 'UN') {
                    $sug = ceil($sug);
                }

                return [$r->pro_codigo, $r->pro_nombre, $r->cat_nombre, $r->dep_nombre, $r->prov_razonsocial ?: '—', (float) $r->pro_stockactual, (float) $r->pro_stockminimo, (float) $sug, round($sug * $r->pro_preciocosto)];
            })->all()];
    }

    private static function r_stock_rotacion(array $f, ?int $suc): array
    {
        $dias = max(1, Carbon::parse($f['desde'])->diffInDays(Carbon::parse($f['hasta'])) + 1);
        $rows = self::ventas($f, $suc)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->selectRaw('p.pro_codigo, p.pro_nombre, p.pro_stockactual, sum(d.det_cantidad) as u')
            ->groupBy('p.pro_id', 'p.pro_codigo', 'p.pro_nombre', 'p.pro_stockactual')->orderByDesc('u')->limit(300)->get();

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Vendido en el período', 'cantidad', true), self::c('Promedio por día', 'cantidad'), self::c('Stock actual', 'cantidad'), self::c('Cobertura (días)', 'entero')],
            'filas' => $rows->map(function ($r) use ($dias) {
                $dia = $r->u / $dias;

                return [$r->pro_codigo, $r->pro_nombre, (float) $r->u, round($dia, 3), (float) $r->pro_stockactual, $dia > 0 ? (int) floor($r->pro_stockactual / $dia) : null];
            })->all()];
    }

    private static function r_stock_sin_movimiento(array $f, ?int $suc): array
    {
        $dias = max(1, (int) ($f['dias'] ?? 60));
        $corte = Carbon::today()->subDays($dias)->format('Y-m-d 00:00:00');
        $ultima = DB::table('detalle_ventas as d')->join('ventas as v', 'v.vta_id', '=', 'd.vta_id')->where('v.vta_estado', 'CONFIRMADA')
            ->selectRaw('d.pro_id, max(v.vta_fecha) as ult')->groupBy('d.pro_id');
        $rows = self::productos($suc)->leftJoinSub($ultima, 'u', 'u.pro_id', '=', 'p.pro_id')->where('p.pro_stockactual', '>', 0)
            ->where(fn ($q) => $q->whereNull('u.ult')->orWhere('u.ult', '<', $corte))
            ->select('p.*', 'c.cat_nombre', 'u.ult')->orderByRaw('p.pro_stockactual * p.pro_preciocosto desc')->limit(1000)->get();

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Categoría'), self::c('Stock', 'cantidad'), self::c('Valor a costo Gs.', 'dinero', true, true), self::c('Valor a venta Gs.', 'dinero', true), self::c('Última venta'), self::c('Días sin vender', 'entero')],
            'filas' => $rows->map(fn ($r) => [$r->pro_codigo, $r->pro_nombre, $r->cat_nombre, (float) $r->pro_stockactual, round($r->pro_stockactual * $r->pro_preciocosto), round($r->pro_stockactual * $r->pro_precioventa),
                $r->ult ? self::fecha($r->ult) : 'Nunca vendido', $r->ult ? (int) Carbon::parse($r->ult)->diffInDays(Carbon::today()) : null])->all()];
    }

    private static function r_stock_vencimientos(array $f, ?int $suc): array
    {
        $dias = max(0, (int) ($f['dias'] ?? 30));
        $limite = Carbon::today()->addDays($dias)->format('Y-m-d');
        $rows = self::productos($suc)->whereNotNull('p.pro_fechavencimiento')->where('p.pro_fechavencimiento', '<=', $limite)->where('p.pro_stockactual', '>', 0)
            ->select('p.*', 'c.cat_nombre')->orderBy('p.pro_fechavencimiento')->get();

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Categoría'), self::c('Vence'), self::c('Días restantes', 'entero'), self::c('Estado'), self::c('Stock', 'cantidad'), self::c('Valor a costo Gs.', 'dinero', true, true)],
            'filas' => $rows->map(function ($r) {
                $d = (int) Carbon::today()->diffInDays(Carbon::parse($r->pro_fechavencimiento), false);

                return [$r->pro_codigo, $r->pro_nombre, $r->cat_nombre, self::fecha($r->pro_fechavencimiento), $d, $d < 0 ? 'VENCIDO' : ($d <= 7 ? 'Crítico' : 'Por vencer'), (float) $r->pro_stockactual, round($r->pro_stockactual * $r->pro_preciocosto)];
            })->all()];
    }

    private static function r_stock_kardex(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $b = trim((string) ($f['producto'] ?? ''));
        $q = DB::table('stock_movimientos as m')->join('productos as p', 'p.pro_id', '=', 'm.pro_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 'm.usu_id')
            ->whereBetween('m.smo_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('p.suc_id', $suc));
        if ($b !== '') {
            $like = '%'.mb_strtolower($b).'%';
            $q->where(fn ($w) => $w->whereRaw('lower(p.pro_nombre) like ?', [$like])->orWhereRaw('lower(p.pro_codigo) like ?', [$like]));
        }
        $rows = $q->select('m.*', 'p.pro_codigo', 'p.pro_nombre', 'u.usu_nombre')->orderBy('p.pro_nombre')->orderBy('m.smo_fecha')->orderBy('m.smo_id')->limit(5000)->get();

        return ['columnas' => [self::c('Fecha'), self::c('Código'), self::c('Producto'), self::c('Movimiento'), self::c('Cantidad', 'cantidad'), self::c('Saldo', 'cantidad'), self::c('Motivo'), self::c('Referencia'), self::c('Usuario')],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->smo_fecha, true), $r->pro_codigo, $r->pro_nombre, str_replace('_', ' ', $r->smo_tipo), (float) $r->smo_cantidad, (float) $r->smo_stock_resultante, $r->smo_motivo, $r->smo_referencia, $r->usu_nombre])->all()];
    }

    private static function r_stock_ajustes(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('stock_movimientos as m')->join('productos as p', 'p.pro_id', '=', 'm.pro_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 'm.usu_id')
            ->whereBetween('m.smo_fecha', [$d, $h])->whereIn('m.smo_tipo', ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA', 'CONTEO', 'DEVOLUCION_PROVEEDOR'])
            ->when($suc, fn ($q) => $q->where('p.suc_id', $suc))
            ->select('m.*', 'p.pro_codigo', 'p.pro_nombre', 'p.pro_preciocosto', 'u.usu_nombre')->orderBy('m.smo_fecha')->limit(5000)->get();

        return ['columnas' => [self::c('Fecha'), self::c('Código'), self::c('Producto'), self::c('Tipo'), self::c('Cantidad', 'cantidad'), self::c('Valor a costo Gs.', 'dinero', true, true), self::c('Saldo resultante', 'cantidad'), self::c('Motivo'), self::c('Usuario')],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->smo_fecha, true), $r->pro_codigo, $r->pro_nombre, str_replace('_', ' ', $r->smo_tipo), (float) $r->smo_cantidad, round($r->smo_cantidad * $r->pro_preciocosto), (float) $r->smo_stock_resultante, $r->smo_motivo, $r->usu_nombre])->all()];
    }

    // ====================================================================== CARTERA

    private static function creditos(?int $suc): Builder
    {
        return DB::table('cuentas_cobrar as cc')->join('ventas as v', 'v.vta_id', '=', 'cc.vta_id')->join('clientes as c', 'c.cli_id', '=', 'cc.cli_id')
            ->where('v.vta_estado', 'CONFIRMADA')->where('cc.cred_saldo_pendiente', '>', 0)->whereNotIn('cc.cred_estado', ['PAGADA', 'ANULADA'])
            ->when($suc, fn ($q) => $q->where('v.suc_id', $suc));
    }

    private static function atraso($venc): int
    {
        return (int) Carbon::parse($venc)->startOfDay()->diffInDays(Carbon::today(), false);
    }

    private static function r_cartera_antiguedad(array $f, ?int $suc): array
    {
        $rows = self::creditos($suc)->select('cc.*', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci', 'c.cli_telefono')->get();
        $g = [];
        foreach ($rows as $r) {
            $k = $r->cli_id;
            $g[$k] ??= ['n' => self::nombre($r->cli_nombre, $r->cli_apellido), 'ruc' => $r->cli_ruc_ci, 'tel' => $r->cli_telefono, 'b' => [0, 0, 0, 0, 0]];
            $a = self::atraso($r->cred_fecha_vencimiento);
            $i = $a <= 0 ? 0 : ($a <= 30 ? 1 : ($a <= 60 ? 2 : ($a <= 90 ? 3 : 4)));
            $g[$k]['b'][$i] += $r->cred_saldo_pendiente;
        }
        uasort($g, fn ($a, $b) => array_sum($b['b']) <=> array_sum($a['b']));

        return ['columnas' => [self::c('Cliente'), self::c('RUC/CI'), self::c('Teléfono'), self::c('Al día', 'dinero', true), self::c('1-30 días', 'dinero', true), self::c('31-60 días', 'dinero', true),
            self::c('61-90 días', 'dinero', true), self::c('Más de 90', 'dinero', true), self::c('Total adeudado', 'dinero', true)],
            'filas' => array_values(array_map(fn ($x) => [$x['n'], $x['ruc'], $x['tel'], ...array_map('round', $x['b']), round(array_sum($x['b']))], $g))];
    }

    private static function r_cartera_pendientes(array $f, ?int $suc): array
    {
        $q = self::creditos($suc)->select('cc.*', 'v.vta_nro_factura', 'c.cli_nombre', 'c.cli_apellido');
        if (trim((string) ($f['cliente'] ?? '')) !== '') {
            $like = '%'.mb_strtolower(trim($f['cliente'])).'%';
            $q->where(fn ($w) => $w->whereRaw("lower(c.cli_nombre || ' ' || coalesce(c.cli_apellido,'')) like ?", [$like])->orWhereRaw('lower(c.cli_ruc_ci) like ?', [$like]));
        }
        $rows = $q->orderBy('c.cli_nombre')->orderBy('cc.cred_fecha_vencimiento')->get();

        return ['columnas' => [self::c('Cliente'), self::c('Venta'), self::c('Emisión'), self::c('Vencimiento'), self::c('Monto original Gs.', 'dinero', true), self::c('Saldo pendiente Gs.', 'dinero', true), self::c('Días de atraso', 'entero'), self::c('Estado')],
            'filas' => $rows->map(function ($r) {
                $a = self::atraso($r->cred_fecha_vencimiento);

                return [self::nombre($r->cli_nombre, $r->cli_apellido), $r->vta_nro_factura ?: '#'.$r->vta_id, self::fecha($r->cred_fecha_emision), self::fecha($r->cred_fecha_vencimiento), round($r->cred_monto_total), round($r->cred_saldo_pendiente), max($a, 0), $a > 0 ? 'VENCIDO' : 'Al día'];
            })->all()];
    }

    private static function r_cartera_cobranzas(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('cobranzas as cb')->join('clientes as c', 'c.cli_id', '=', 'cb.cli_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 'cb.usu_id')
            ->where('cb.cob_estado', 'ACTIVA')->whereBetween('cb.cob_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('cb.suc_id', $suc))
            ->select('cb.*', 'c.cli_nombre', 'c.cli_apellido', 'u.usu_nombre')->orderBy('cb.cob_fecha')->get();

        return ['columnas' => [self::c('Fecha'), self::c('Cobro N°', 'entero'), self::c('Cliente'), self::c('Forma de pago'), self::c('Cobró'), self::c('Monto Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->cob_fecha, true), (int) $r->cob_id, self::nombre($r->cli_nombre, $r->cli_apellido), $r->cob_formapago, $r->usu_nombre, round($r->cob_monto_total)])->all()];
    }

    private static function r_cartera_top_clientes(array $f, ?int $suc): array
    {
        $rows = self::ventas($f, $suc)->join('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->selectRaw('c.cli_nombre, c.cli_apellido, c.cli_ruc_ci, count(*) as n, sum(v.vta_total) as total, max(v.vta_fecha) as ult')
            ->groupBy('c.cli_id', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci')->orderByDesc('total')->limit(200)->get();
        $suma = $rows->sum('total');

        return ['columnas' => [self::c('#', 'entero'), self::c('Cliente'), self::c('RUC/CI'), self::c('Compras', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('Ticket promedio', 'dinero'), self::c('% del total', 'porc'), self::c('Última compra')],
            'filas' => $rows->values()->map(fn ($r, $i) => [$i + 1, self::nombre($r->cli_nombre, $r->cli_apellido), $r->cli_ruc_ci, (int) $r->n, round($r->total), round($r->total / max($r->n, 1)), self::pct($r->total, $suma), self::fecha($r->ult)])->all()];
    }

    private static function r_cartera_inactivos(array $f, ?int $suc): array
    {
        $dias = max(1, (int) ($f['dias'] ?? 90));
        $corte = Carbon::today()->subDays($dias)->format('Y-m-d 00:00:00');
        $rows = DB::table('ventas as v')->join('clientes as c', 'c.cli_id', '=', 'v.cli_id')->where('v.vta_estado', 'CONFIRMADA')->when($suc, fn ($q) => $q->where('v.suc_id', $suc))
            ->selectRaw('c.cli_nombre, c.cli_apellido, c.cli_ruc_ci, c.cli_telefono, count(*) as n, sum(v.vta_total) as total, max(v.vta_fecha) as ult')
            ->groupBy('c.cli_id', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci', 'c.cli_telefono')->havingRaw('max(v.vta_fecha) < ?', [$corte])->orderByDesc('total')->limit(500)->get();

        return ['columnas' => [self::c('Cliente'), self::c('RUC/CI'), self::c('Teléfono'), self::c('Compras históricas', 'entero', true), self::c('Total comprado Gs.', 'dinero', true), self::c('Última compra'), self::c('Días sin comprar', 'entero')],
            'filas' => $rows->map(fn ($r) => [self::nombre($r->cli_nombre, $r->cli_apellido), $r->cli_ruc_ci, $r->cli_telefono, (int) $r->n, round($r->total), self::fecha($r->ult), (int) Carbon::parse($r->ult)->diffInDays(Carbon::today())])->all()];
    }

    private static function r_cartera_limites(array $f, ?int $suc): array
    {
        $deuda = DB::table('cuentas_cobrar as cc')->join('ventas as v', 'v.vta_id', '=', 'cc.vta_id')->where('v.vta_estado', 'CONFIRMADA')->whereNotIn('cc.cred_estado', ['PAGADA', 'ANULADA'])
            ->selectRaw('cc.cli_id, sum(cc.cred_saldo_pendiente) as deuda')->groupBy('cc.cli_id')->pluck('deuda', 'cli_id');
        $rows = DB::table('clientes as c')->where(fn ($q) => $q->where('c.cli_permitir_credito', true)->orWhere('c.cli_bloqueado', true))->orderBy('c.cli_nombre')->get();
        $filas = [];
        foreach ($rows as $r) {
            $d = (float) ($deuda[$r->cli_id] ?? 0);
            $lim = (float) $r->cli_limite_credito;
            if ($d <= 0 && ! $r->cli_bloqueado && $lim <= 0) {
                continue;
            }
            $estado = $r->cli_bloqueado ? 'BLOQUEADO' : ($lim > 0 && $d > $lim ? 'EXCEDIDO' : ($lim > 0 && $d >= $lim * 0.8 ? 'Cerca del límite' : 'OK'));
            $filas[] = [self::nombre($r->cli_nombre, $r->cli_apellido), $r->cli_ruc_ci, $lim, $d, max($lim - $d, 0), $lim > 0 ? self::pct($d, $lim) : null, $estado];
        }
        usort($filas, fn ($a, $b) => $b[3] <=> $a[3]);

        return ['columnas' => [self::c('Cliente'), self::c('RUC/CI'), self::c('Límite Gs.', 'dinero', true), self::c('Deuda actual Gs.', 'dinero', true), self::c('Disponible Gs.', 'dinero', true), self::c('% usado', 'porc'), self::c('Estado')], 'filas' => $filas];
    }

    // ====================================================================== COMPRAS

    private static function compras(array $f, ?int $suc, bool $anuladas = false): Builder
    {
        [$d, $h] = self::rango($f);

        return DB::table('compras as co')->where('co.com_estado', $anuladas ? 'ANULADA' : 'REGISTRADA')
            ->whereBetween('co.com_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('co.suc_id', $suc));
    }

    private static function r_compras_periodo(array $f, ?int $suc): array
    {
        $porMes = ($f['agrupar'] ?? 'dia') === 'mes';
        $k = self::expr($porMes ? 'mes' : 'dia', 'co.com_fecha');
        $rows = self::compras($f, $suc)->selectRaw("$k as k, count(*) as n, sum(co.com_total) as total,
            sum(case when co.com_tipo = 'CONTADO' then co.com_total else 0 end) as contado,
            sum(case when co.com_tipo = 'CREDITO' then co.com_total else 0 end) as credito")->groupByRaw($k)->orderByRaw($k)->get();
        $anul = self::compras($f, $suc, true)->selectRaw("$k as k, count(*) as n")->groupByRaw($k)->pluck('n', 'k');

        return ['columnas' => [self::c($porMes ? 'Mes' : 'Día'), self::c('Compras', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('Contado', 'dinero', true), self::c('Crédito', 'dinero', true), self::c('Anuladas', 'entero', true)],
            'filas' => $rows->map(fn ($r) => [$porMes ? Carbon::createFromFormat('Y-m', $r->k)->locale('es')->translatedFormat('F Y') : Carbon::parse($r->k)->locale('es')->translatedFormat('D d/m/Y'),
                (int) $r->n, round($r->total), round($r->contado), round($r->credito), (int) ($anul[$r->k] ?? 0)])->all()];
    }

    private static function r_compras_proveedor(array $f, ?int $suc): array
    {
        $rows = self::compras($f, $suc)->join('proveedores as pv', 'pv.prov_id', '=', 'co.prov_id')
            ->selectRaw('pv.prov_razonsocial, pv.prov_ruc, count(*) as n, sum(co.com_total) as total, max(co.com_fecha) as ult')
            ->groupBy('pv.prov_id', 'pv.prov_razonsocial', 'pv.prov_ruc')->orderByDesc('total')->get();
        $suma = $rows->sum('total');

        return ['columnas' => [self::c('Proveedor'), self::c('RUC'), self::c('Compras', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('% del total', 'porc'), self::c('Compra promedio', 'dinero'), self::c('Última compra')],
            'filas' => $rows->map(fn ($r) => [$r->prov_razonsocial, $r->prov_ruc, (int) $r->n, round($r->total), self::pct($r->total, $suma), round($r->total / max($r->n, 1)), self::fecha($r->ult)])->all()];
    }

    private static function r_compras_listado(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('compras as co')->join('proveedores as pv', 'pv.prov_id', '=', 'co.prov_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 'co.usu_id')
            ->whereBetween('co.com_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('co.suc_id', $suc))
            ->select('co.*', 'pv.prov_razonsocial', 'u.usu_nombre')->orderBy('co.com_fecha')->limit(20000)->get();

        return ['columnas' => [self::c('Fecha'), self::c('Comprobante'), self::c('Timbrado'), self::c('Proveedor'), self::c('Tipo'), self::c('Estado'), self::c('Registró'), self::c('Total Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->com_fecha, true), $r->com_nro_documento ?: '#'.$r->com_id, $r->com_timbrado, $r->prov_razonsocial, $r->com_tipo, $r->com_estado, $r->usu_nombre, $r->com_estado === 'ANULADA' ? 0 : round($r->com_total)])->all()];
    }

    private static function r_compras_producto(array $f, ?int $suc): array
    {
        $rows = self::compras($f, $suc)->join('detalle_compras as dc', 'dc.com_id', '=', 'co.com_id')->join('productos as p', 'p.pro_id', '=', 'dc.pro_id')
            ->selectRaw('p.pro_codigo, p.pro_nombre, sum(dc.dco_cantidad) as u, sum(dc.dco_subtotal) as total')
            ->groupBy('p.pro_id', 'p.pro_codigo', 'p.pro_nombre')->orderByDesc('total')->limit(500)->get();

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Cantidad comprada', 'cantidad', true), self::c('Costo promedio Gs.', 'dinero'), self::c('Total Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [$r->pro_codigo, $r->pro_nombre, (float) $r->u, $r->u > 0 ? round($r->total / $r->u) : 0, round($r->total)])->all()];
    }

    private static function r_compras_costos(array $f, ?int $suc): array
    {
        $rows = self::compras($f, $suc)->join('detalle_compras as dc', 'dc.com_id', '=', 'co.com_id')->join('productos as p', 'p.pro_id', '=', 'dc.pro_id')
            ->select('p.pro_id', 'p.pro_codigo', 'p.pro_nombre', 'dc.dco_costo', 'co.com_fecha', 'dc.dco_id')->orderBy('co.com_fecha')->orderBy('dc.dco_id')->limit(50000)->get();
        $g = [];
        foreach ($rows as $r) {
            $x = &$g[$r->pro_id];
            $x ??= ['c' => $r->pro_codigo, 'n' => $r->pro_nombre, 'primero' => (float) $r->dco_costo, 'min' => (float) $r->dco_costo, 'max' => (float) $r->dco_costo, 'ult' => 0.0, 'cant' => 0];
            $x['min'] = min($x['min'], (float) $r->dco_costo);
            $x['max'] = max($x['max'], (float) $r->dco_costo);
            $x['ult'] = (float) $r->dco_costo;
            $x['cant']++;
            unset($x);
        }
        $filas = array_map(fn ($x) => [$x['c'], $x['n'], $x['cant'], round($x['primero']), round($x['ult']), round($x['min']), round($x['max']), $x['primero'] > 0 ? round(($x['ult'] - $x['primero']) / $x['primero'] * 100, 1) : null], array_values($g));
        usort($filas, fn ($a, $b) => abs($b[7] ?? 0) <=> abs($a[7] ?? 0));

        return ['columnas' => [self::c('Código'), self::c('Producto'), self::c('Compras', 'entero'), self::c('Primer costo Gs.', 'dinero'), self::c('Último costo Gs.', 'dinero'), self::c('Mínimo Gs.', 'dinero'), self::c('Máximo Gs.', 'dinero'), self::c('Variación %', 'porc')], 'filas' => $filas];
    }

    private static function r_compras_anuladas(array $f, ?int $suc): array
    {
        $rows = self::compras($f, $suc, true)->join('proveedores as pv', 'pv.prov_id', '=', 'co.prov_id')->leftJoin('usuarios as a', 'a.usu_id', '=', 'co.com_anulada_por')
            ->select('co.*', 'pv.prov_razonsocial', 'a.usu_nombre as anulo')->orderBy('co.com_fecha')->get();

        return ['columnas' => [self::c('Fecha compra'), self::c('Comprobante'), self::c('Proveedor'), self::c('Total Gs.', 'dinero', true), self::c('Anulada por'), self::c('Fecha anulación'), self::c('Motivo')],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->com_fecha, true), $r->com_nro_documento ?: '#'.$r->com_id, $r->prov_razonsocial, round($r->com_total), $r->anulo, self::fecha($r->com_anulada_fecha, true), $r->com_motivo_anulacion])->all()];
    }

    private static function deudas(?int $suc): Builder
    {
        return DB::table('cuentas_pagar as cp')->join('compras as co', 'co.com_id', '=', 'cp.com_id')->join('proveedores as pv', 'pv.prov_id', '=', 'cp.prov_id')
            ->where('co.com_estado', 'REGISTRADA')->where('cp.cpa_saldo_pendiente', '>', 0)->whereNotIn('cp.cpa_estado', ['PAGADA', 'ANULADA'])
            ->when($suc, fn ($q) => $q->where('co.suc_id', $suc));
    }

    private static function r_pagar_antiguedad(array $f, ?int $suc): array
    {
        $g = [];
        foreach (self::deudas($suc)->select('cp.*', 'pv.prov_razonsocial', 'pv.prov_ruc', 'pv.prov_telefono')->get() as $r) {
            $g[$r->prov_id] ??= ['n' => $r->prov_razonsocial, 'ruc' => $r->prov_ruc, 'tel' => $r->prov_telefono, 'b' => [0, 0, 0, 0, 0]];
            $a = $r->cpa_fecha_vencimiento ? self::atraso($r->cpa_fecha_vencimiento) : 0;
            $g[$r->prov_id]['b'][$a <= 0 ? 0 : ($a <= 30 ? 1 : ($a <= 60 ? 2 : ($a <= 90 ? 3 : 4)))] += $r->cpa_saldo_pendiente;
        }
        uasort($g, fn ($a, $b) => array_sum($b['b']) <=> array_sum($a['b']));

        return ['columnas' => [self::c('Proveedor'), self::c('RUC'), self::c('Teléfono'), self::c('Al día', 'dinero', true), self::c('1-30 días', 'dinero', true), self::c('31-60 días', 'dinero', true), self::c('61-90 días', 'dinero', true), self::c('Más de 90', 'dinero', true), self::c('Total a pagar', 'dinero', true)],
            'filas' => array_values(array_map(fn ($x) => [$x['n'], $x['ruc'], $x['tel'], ...array_map('round', $x['b']), round(array_sum($x['b']))], $g))];
    }

    private static function r_pagar_pendientes(array $f, ?int $suc): array
    {
        $rows = self::deudas($suc)->select('cp.*', 'co.com_nro_documento', 'co.com_fecha', 'pv.prov_razonsocial')->orderByRaw('cp.cpa_fecha_vencimiento is null')->orderBy('cp.cpa_fecha_vencimiento')->get();

        return ['columnas' => [self::c('Proveedor'), self::c('Comprobante'), self::c('Fecha compra'), self::c('Vencimiento'), self::c('Monto original Gs.', 'dinero', true), self::c('Saldo Gs.', 'dinero', true), self::c('Días de atraso', 'entero'), self::c('Estado')],
            'filas' => $rows->map(function ($r) {
                $a = $r->cpa_fecha_vencimiento ? self::atraso($r->cpa_fecha_vencimiento) : 0;

                return [$r->prov_razonsocial, $r->com_nro_documento ?: '#'.$r->com_id, self::fecha($r->com_fecha), self::fecha($r->cpa_fecha_vencimiento) ?: 'Sin vencimiento', round($r->cpa_monto_total), round($r->cpa_saldo_pendiente), max($a, 0), $a > 0 ? 'VENCIDO' : 'Al día'];
            })->all()];
    }

    private static function r_pagos_proveedores(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('pagos_proveedores as pg')->join('proveedores as pv', 'pv.prov_id', '=', 'pg.prov_id')->join('cuentas_pagar as cp', 'cp.cpa_id', '=', 'pg.cpa_id')->join('compras as co', 'co.com_id', '=', 'cp.com_id')
            ->leftJoin('usuarios as u', 'u.usu_id', '=', 'pg.usu_id')->where('pg.pag_estado', 'ACTIVO')->whereBetween('pg.pag_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('co.suc_id', $suc))
            ->select('pg.*', 'pv.prov_razonsocial', 'co.com_nro_documento', 'co.com_id', 'u.usu_nombre')->orderBy('pg.pag_fecha')->get();

        return ['columnas' => [self::c('Fecha'), self::c('Proveedor'), self::c('Compra'), self::c('Forma de pago'), self::c('Referencia'), self::c('Pagó'), self::c('Monto Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->pag_fecha, true), $r->prov_razonsocial, $r->com_nro_documento ?: '#'.$r->com_id, $r->pag_forma_pago, $r->pag_referencia, $r->usu_nombre, round($r->pag_monto)])->all()];
    }

    // ====================================================================== CAJA

    private static function sesiones(array $f, ?int $suc, bool $listado = true): Builder
    {
        [$d, $h] = self::rango($f);

        $q = DB::table('caja_sesiones as s')->join('cajas as cj', 'cj.caj_id', '=', 's.caj_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 's.usu_id')
            ->whereBetween('s.ses_fecha_apertura', [$d, $h])->when($suc, fn ($q) => $q->where('cj.suc_id', $suc));

        return $listado ? $q->select('s.*', 'cj.caj_nombre', 'u.usu_nombre', 'u.usu_apellido') : $q;
    }

    private static function filaSesion($r): array
    {
        return [self::fecha($r->ses_fecha_apertura, true), self::fecha($r->ses_fecha_cierre, true), $r->caj_nombre, self::nombre($r->usu_nombre, $r->usu_apellido), $r->ses_estado,
            round($r->ses_monto_inicial_gs), $r->ses_esperado_gs === null ? null : round($r->ses_esperado_gs), $r->ses_monto_cierre_gs === null ? null : round($r->ses_monto_cierre_gs), $r->ses_diferencia_gs === null ? null : round($r->ses_diferencia_gs)];
    }

    private static function colsSesion(): array
    {
        return [self::c('Apertura'), self::c('Cierre'), self::c('Caja'), self::c('Cajero'), self::c('Estado'), self::c('Monto inicial Gs.', 'dinero', true), self::c('Esperado Gs.', 'dinero', true), self::c('Contado Gs.', 'dinero', true), self::c('Diferencia Gs.', 'dinero', true)];
    }

    private static function r_caja_sesiones(array $f, ?int $suc): array
    {
        return ['columnas' => self::colsSesion(), 'filas' => self::sesiones($f, $suc)->orderBy('s.ses_fecha_apertura')->get()->map(fn ($r) => self::filaSesion($r))->all()];
    }

    private static function r_caja_diferencias(array $f, ?int $suc): array
    {
        $rows = self::sesiones($f, $suc)->where('s.ses_estado', 'CERRADA')->whereNotNull('s.ses_diferencia_gs')->where('s.ses_diferencia_gs', '<>', 0)->orderBy('s.ses_fecha_apertura')->get();
        $cols = self::colsSesion();
        $cols[] = self::c('Resultado');

        return ['columnas' => $cols, 'filas' => $rows->map(fn ($r) => [...self::filaSesion($r), $r->ses_diferencia_gs < 0 ? 'FALTANTE' : 'SOBRANTE'])->all()];
    }

    private static function movs(array $f, ?int $suc): Builder
    {
        [$d, $h] = self::rango($f);

        return DB::table('caja_movimientos as m')->join('caja_sesiones as s', 's.ses_id', '=', 'm.ses_id')->join('cajas as cj', 'cj.caj_id', '=', 's.caj_id')
            ->where('m.mov_moneda', 'GS')->whereBetween('m.mov_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('cj.suc_id', $suc));
    }

    private static function r_caja_flujo(array $f, ?int $suc): array
    {
        $k = self::expr('dia', 'm.mov_fecha');
        $rows = self::movs($f, $suc)->selectRaw("$k as k, sum(case when m.mov_tipo = 'INGRESO' then m.mov_monto else 0 end) as ing, sum(case when m.mov_tipo = 'EGRESO' then m.mov_monto else 0 end) as egr")
            ->groupByRaw($k)->orderByRaw($k)->get();
        $acum = 0;

        return ['columnas' => [self::c('Día'), self::c('Ingresos Gs.', 'dinero', true), self::c('Egresos Gs.', 'dinero', true), self::c('Neto Gs.', 'dinero', true), self::c('Acumulado Gs.', 'dinero')],
            'filas' => $rows->map(function ($r) use (&$acum) {
                $acum += $r->ing - $r->egr;

                return [Carbon::parse($r->k)->locale('es')->translatedFormat('D d/m/Y'), round($r->ing), round($r->egr), round($r->ing - $r->egr), round($acum)];
            })->all()];
    }

    private static function r_caja_formapago(array $f, ?int $suc): array
    {
        $rows = self::movs($f, $suc)->selectRaw("coalesce(m.mov_forma_pago,'EFECTIVO') as fp, sum(case when m.mov_tipo = 'INGRESO' then m.mov_monto else 0 end) as ing, sum(case when m.mov_tipo = 'EGRESO' then m.mov_monto else 0 end) as egr, count(*) as n")
            ->groupByRaw("coalesce(m.mov_forma_pago,'EFECTIVO')")->orderByDesc('ing')->get();

        return ['columnas' => [self::c('Forma de pago'), self::c('Movimientos', 'entero', true), self::c('Ingresos Gs.', 'dinero', true), self::c('Egresos Gs.', 'dinero', true), self::c('Neto Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [$r->fp, (int) $r->n, round($r->ing), round($r->egr), round($r->ing - $r->egr)])->all()];
    }

    private static function r_caja_extras(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $rows = DB::table('ingresos_egresos as ie')->join('caja_sesiones as s', 's.ses_id', '=', 'ie.ses_id')->join('cajas as cj', 'cj.caj_id', '=', 's.caj_id')->leftJoin('usuarios as u', 'u.usu_id', '=', 's.usu_id')
            ->whereBetween('ie.ie_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('cj.suc_id', $suc))
            ->select('ie.*', 'cj.caj_nombre', 'u.usu_nombre')->orderBy('ie.ie_fecha')->get();

        return ['columnas' => [self::c('Fecha'), self::c('Caja'), self::c('Cajero'), self::c('Tipo'), self::c('Concepto'), self::c('Ingreso Gs.', 'dinero', true), self::c('Egreso Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::fecha($r->ie_fecha, true), $r->caj_nombre, $r->usu_nombre, $r->ie_tipo, $r->ie_concepto, $r->ie_tipo === 'INGRESO' ? round($r->ie_monto) : 0, $r->ie_tipo === 'EGRESO' ? round($r->ie_monto) : 0])->all()];
    }

    // ====================================================================== FISCAL

    /** Filas normalizadas [fecha, tipo, timbrado, nro, ruc, nombre, g10, g5, ex] con IVA incluido; mismo criterio que el Libro IVA de Contabilidad. */
    private static function baseVentas(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $q = DB::table('ventas as v')->leftJoin('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->leftJoin('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')->leftJoin('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->whereBetween('v.vta_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('v.suc_id', $suc))
            ->selectRaw('v.vta_id, v.vta_fecha, v.vta_tipo, v.vta_estado, v.vta_timbrado, v.vta_nro_factura, c.cli_nombre, c.cli_apellido, c.cli_ruc_ci,
                coalesce(sum(case when p.pro_tipo_iva = 10 then d.det_subtotal else 0 end), 0) as g10,
                coalesce(sum(case when p.pro_tipo_iva = 5 then d.det_subtotal else 0 end), 0) as g5,
                coalesce(sum(case when p.pro_tipo_iva is null or p.pro_tipo_iva not in (5, 10) then d.det_subtotal else 0 end), 0) as ex')
            ->groupBy('v.vta_id', 'v.vta_fecha', 'v.vta_tipo', 'v.vta_estado', 'v.vta_timbrado', 'v.vta_nro_factura', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci')
            ->orderBy('v.vta_fecha')->orderBy('v.vta_id')->get();

        $filas = [];
        foreach ($q as $v) {
            $anul = $v->vta_estado === 'ANULADA';
            $filas[] = [$v->vta_fecha, $anul ? 'Anulada' : ($v->vta_tipo === 'CREDITO' ? 'Factura crédito' : 'Factura contado'), (string) $v->vta_timbrado, $v->vta_nro_factura ?: 'Venta '.$v->vta_id,
                (string) $v->cli_ruc_ci, self::nombre($v->cli_nombre, $v->cli_apellido), $anul ? 0.0 : (float) $v->g10, $anul ? 0.0 : (float) $v->g5, $anul ? 0.0 : (float) $v->ex];
        }

        $devs = DB::table('devoluciones as dv')->join('ventas as v', 'v.vta_id', '=', 'dv.vta_id')->leftJoin('clientes as c', 'c.cli_id', '=', 'v.cli_id')
            ->join('detalle_devoluciones as dd', 'dd.dev_id', '=', 'dv.dev_id')->join('productos as p', 'p.pro_id', '=', 'dd.pro_id')
            ->whereBetween('dv.dev_fecha', [$d, $h])->where('v.vta_estado', 'CONFIRMADA')->when($suc, fn ($q) => $q->where('v.suc_id', $suc))
            ->selectRaw('dv.dev_id, dv.dev_fecha, v.vta_timbrado, v.vta_nro_factura, v.vta_id, c.cli_nombre, c.cli_apellido, c.cli_ruc_ci,
                sum(case when p.pro_tipo_iva = 10 then dd.ddv_subtotal else 0 end) as g10, sum(case when p.pro_tipo_iva = 5 then dd.ddv_subtotal else 0 end) as g5,
                sum(case when p.pro_tipo_iva not in (5, 10) then dd.ddv_subtotal else 0 end) as ex')
            ->groupBy('dv.dev_id', 'dv.dev_fecha', 'v.vta_timbrado', 'v.vta_nro_factura', 'v.vta_id', 'c.cli_nombre', 'c.cli_apellido', 'c.cli_ruc_ci')->get();
        foreach ($devs as $x) {
            $filas[] = [$x->dev_fecha, 'Devolución', (string) $x->vta_timbrado, 'Dev. '.$x->dev_id.' (fact. '.($x->vta_nro_factura ?: $x->vta_id).')', (string) $x->cli_ruc_ci,
                self::nombre($x->cli_nombre, $x->cli_apellido), -(float) $x->g10, -(float) $x->g5, -(float) $x->ex];
        }
        usort($filas, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));

        return $filas;
    }

    private static function baseCompras(array $f, ?int $suc): array
    {
        $rows = self::compras($f, $suc)->join('proveedores as pv', 'pv.prov_id', '=', 'co.prov_id')
            ->leftJoin('detalle_compras as dc', 'dc.com_id', '=', 'co.com_id')->leftJoin('productos as p', 'p.pro_id', '=', 'dc.pro_id')
            ->selectRaw('co.com_id, co.com_fecha, co.com_tipo, co.com_nro_documento, co.com_timbrado, pv.prov_razonsocial, pv.prov_ruc,
                coalesce(sum(case when p.pro_tipo_iva = 10 then dc.dco_subtotal else 0 end), 0) as g10,
                coalesce(sum(case when p.pro_tipo_iva = 5 then dc.dco_subtotal else 0 end), 0) as g5,
                coalesce(sum(case when p.pro_tipo_iva is null or p.pro_tipo_iva not in (5, 10) then dc.dco_subtotal else 0 end), 0) as ex')
            ->groupBy('co.com_id', 'co.com_fecha', 'co.com_tipo', 'co.com_nro_documento', 'co.com_timbrado', 'pv.prov_razonsocial', 'pv.prov_ruc')
            ->orderBy('co.com_fecha')->orderBy('co.com_id')->get();

        return $rows->map(fn ($c) => [$c->com_fecha, $c->com_tipo === 'CREDITO' ? 'Factura crédito' : 'Factura contado', (string) $c->com_timbrado, $c->com_nro_documento ?: 'Compra '.$c->com_id,
            (string) $c->prov_ruc, (string) $c->prov_razonsocial, (float) $c->g10, (float) $c->g5, (float) $c->ex])->all();
    }

    private static function colsLibro(string $quien): array
    {
        return [self::c('Fecha'), self::c('Tipo'), self::c('Timbrado'), self::c('Número'), self::c('RUC/CI'), self::c($quien), self::c('Gravado 10% Gs.', 'dinero', true), self::c('IVA 10% Gs.', 'dinero', true),
            self::c('Gravado 5% Gs.', 'dinero', true), self::c('IVA 5% Gs.', 'dinero', true), self::c('Exentas Gs.', 'dinero', true), self::c('Total Gs.', 'dinero', true)];
    }

    /** [g10, g5, ex] con IVA incluido → base10, iva10, base5, iva5, exento, total */
    private static function desglose(float $g10, float $g5, float $ex): array
    {
        $i10 = round($g10 / 11, 2);
        $i5 = round($g5 / 21, 2);

        return [round($g10 - $i10), round($i10), round($g5 - $i5), round($i5), round($ex), round($g10 + $g5 + $ex)];
    }

    private static function r_fiscal_libro_ventas(array $f, ?int $suc): array
    {
        return ['columnas' => self::colsLibro('Cliente'), 'filas' => array_map(fn ($r) => [self::fecha($r[0]), $r[1], $r[2], $r[3], $r[4], $r[5], ...self::desglose($r[6], $r[7], $r[8])], self::baseVentas($f, $suc))];
    }

    private static function r_fiscal_libro_compras(array $f, ?int $suc): array
    {
        return ['columnas' => self::colsLibro('Proveedor'), 'filas' => array_map(fn ($r) => [self::fecha($r[0]), $r[1], $r[2], $r[3], $r[4], $r[5], ...self::desglose($r[6], $r[7], $r[8])], self::baseCompras($f, $suc))];
    }

    private static function r_fiscal_resumen_iva(array $f, ?int $suc): array
    {
        $m = [];
        foreach (self::baseVentas($f, $suc) as $r) {
            $k = substr((string) $r[0], 0, 7);
            $m[$k] ??= array_fill(0, 7, 0.0);
            [$b10, $i10, $b5, $i5, $ex] = self::desglose($r[6], $r[7], $r[8]);
            $m[$k][0] += $b10 + $i10; $m[$k][1] += $b5 + $i5; $m[$k][2] += $ex; $m[$k][3] += $i10 + $i5;
        }
        foreach (self::baseCompras($f, $suc) as $r) {
            $k = substr((string) $r[0], 0, 7);
            $m[$k] ??= array_fill(0, 7, 0.0);
            [, $i10, , $i5] = self::desglose($r[6], $r[7], $r[8]);
            $m[$k][4] += $i10 + $i5;
        }
        ksort($m);
        $filas = [];
        foreach ($m as $k => $x) {
            $filas[] = [Carbon::createFromFormat('Y-m', $k)->locale('es')->translatedFormat('F Y'), round($x[0]), round($x[1]), round($x[2]), round($x[3]), round($x[4]), round($x[3] - $x[4])];
        }

        return ['columnas' => [self::c('Mes'), self::c('Ventas gravadas 10% Gs.', 'dinero', true), self::c('Ventas gravadas 5% Gs.', 'dinero', true), self::c('Ventas exentas Gs.', 'dinero', true),
            self::c('Débito fiscal (IVA ventas) Gs.', 'dinero', true), self::c('Crédito fiscal (IVA compras) Gs.', 'dinero', true), self::c('Saldo: a pagar (+) / a favor (-) Gs.', 'dinero', true)], 'filas' => $filas];
    }

    private static function agruparRuc(array $base): array
    {
        $g = [];
        foreach ($base as $r) {
            if ($r[1] === 'Anulada') {
                continue;
            }
            $k = $r[4] !== '' ? $r[4] : $r[5];
            $g[$k] ??= ['ruc' => $r[4], 'n' => $r[5], 'c' => 0, 'g10' => 0.0, 'g5' => 0.0, 'ex' => 0.0];
            $g[$k]['c'] += $r[1] === 'Devolución' ? 0 : 1;
            $g[$k]['g10'] += $r[6]; $g[$k]['g5'] += $r[7]; $g[$k]['ex'] += $r[8];
        }
        uasort($g, fn ($a, $b) => ($b['g10'] + $b['g5'] + $b['ex']) <=> ($a['g10'] + $a['g5'] + $a['ex']));

        return array_values(array_map(function ($x) {
            [$b10, $i10, $b5, $i5, $ex, $t] = self::desglose($x['g10'], $x['g5'], $x['ex']);

            return [$x['ruc'], $x['n'], $x['c'], $b10 + $b5, $i10, $i5, $ex, $t];
        }, $g));
    }

    private static function colsRuc(string $quien): array
    {
        return [self::c('RUC/CI'), self::c($quien), self::c('Comprobantes', 'entero', true), self::c('Base gravada Gs.', 'dinero', true), self::c('IVA 10% Gs.', 'dinero', true), self::c('IVA 5% Gs.', 'dinero', true), self::c('Exentas Gs.', 'dinero', true), self::c('Total Gs.', 'dinero', true)];
    }

    private static function r_fiscal_ventas_ruc(array $f, ?int $suc): array
    {
        return ['columnas' => self::colsRuc('Cliente'), 'filas' => self::agruparRuc(self::baseVentas($f, $suc))];
    }

    private static function r_fiscal_compras_ruc(array $f, ?int $suc): array
    {
        return ['columnas' => self::colsRuc('Proveedor'), 'filas' => self::agruparRuc(self::baseCompras($f, $suc))];
    }

    /** @return array<string, array{suc:string, punto:string, timbrado:string, nums:list<int>, anuladas:int}> */
    private static function emitidas(array $f, ?int $suc): array
    {
        $rows = self::ventasTodas($f, $suc)->join('sucursales as sc', 'sc.suc_id', '=', 'v.suc_id')->whereNotNull('v.vta_nro_factura')->where('v.vta_nro_factura', '<>', '')
            ->select('v.vta_timbrado', 'v.vta_nro_factura', 'v.vta_estado', 'sc.suc_nombre', 'sc.suc_est_punto_exp', 'v.suc_id')->orderBy('v.vta_id')->get();
        $g = [];
        foreach ($rows as $r) {
            if (! ctype_digit((string) $r->vta_nro_factura)) {
                continue;
            }
            $k = $r->suc_id.'|'.$r->vta_timbrado;
            $g[$k] ??= ['suc' => $r->suc_nombre, 'punto' => (string) $r->suc_est_punto_exp, 'timbrado' => (string) $r->vta_timbrado, 'nums' => [], 'anuladas' => 0];
            $g[$k]['nums'][] = (int) $r->vta_nro_factura;
            $g[$k]['anuladas'] += $r->vta_estado === 'ANULADA' ? 1 : 0;
        }

        return $g;
    }

    private static function ventasTodas(array $f, ?int $suc): Builder
    {
        [$d, $h] = self::rango($f);

        return DB::table('ventas as v')->whereBetween('v.vta_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('v.suc_id', $suc));
    }

    private static function r_fiscal_timbrados(array $f, ?int $suc): array
    {
        $filas = [];
        foreach (self::emitidas($f, $suc) as $x) {
            $n = array_values(array_unique($x['nums']));
            sort($n);
            $faltan = ($n[count($n) - 1] - $n[0] + 1) - count($n);
            $filas[] = [$x['suc'], $x['timbrado'], $x['punto'], str_pad((string) $n[0], 7, '0', STR_PAD_LEFT), str_pad((string) $n[count($n) - 1], 7, '0', STR_PAD_LEFT), count($n), $x['anuladas'], $faltan];
        }

        return ['columnas' => [self::c('Sucursal'), self::c('Timbrado'), self::c('Establecimiento-Punto'), self::c('Primer número'), self::c('Último número'), self::c('Emitidas', 'entero', true), self::c('Anuladas', 'entero', true), self::c('Números faltantes', 'entero', true)], 'filas' => $filas];
    }

    private static function r_fiscal_huecos(array $f, ?int $suc): array
    {
        $filas = [];
        foreach (self::emitidas($f, $suc) as $x) {
            $n = array_values(array_unique($x['nums']));
            sort($n);
            for ($i = 1; $i < count($n); $i++) {
                if ($n[$i] - $n[$i - 1] > 1) {
                    $d = $n[$i - 1] + 1;
                    $h = $n[$i] - 1;
                    $filas[] = [$x['suc'], $x['timbrado'], $x['punto'], str_pad((string) $d, 7, '0', STR_PAD_LEFT), str_pad((string) $h, 7, '0', STR_PAD_LEFT), $h - $d + 1];
                }
            }
        }

        return ['columnas' => [self::c('Sucursal'), self::c('Timbrado'), self::c('Establecimiento-Punto'), self::c('Falta desde'), self::c('Falta hasta'), self::c('Cantidad', 'entero', true)], 'filas' => $filas];
    }

    private static function r_fiscal_vigencia(array $f, ?int $suc): array
    {
        $rows = DB::table('sucursales as sc')->where('sc.suc_activa', true)->when($suc, fn ($q) => $q->where('sc.suc_id', $suc))->orderBy('sc.suc_nombre')->get();

        return ['columnas' => [self::c('Sucursal'), self::c('Timbrado'), self::c('Establecimiento-Punto'), self::c('Vigente desde'), self::c('Vence'), self::c('Días restantes', 'entero'), self::c('Próximo número'), self::c('Estado')],
            'filas' => $rows->map(function ($r) {
                $d = $r->suc_timbrado_fin ? (int) Carbon::today()->diffInDays(Carbon::parse($r->suc_timbrado_fin), false) : null;
                $estado = ! $r->suc_timbrado ? 'SIN TIMBRADO' : ($d === null ? 'Sin fecha de vencimiento' : ($d < 0 ? 'VENCIDO' : ($d <= 30 ? 'POR VENCER' : 'Vigente')));

                return [$r->suc_nombre, $r->suc_timbrado ?: '—', $r->suc_est_punto_exp, self::fecha($r->suc_timbrado_inicio), self::fecha($r->suc_timbrado_fin), $d, str_pad((string) $r->suc_factura_secuencia, 7, '0', STR_PAD_LEFT), $estado];
            })->all()];
    }

    // ====================================================================== POR USUARIO

    private static function ventasUsuario(array $f, ?int $suc): Builder
    {
        $q = self::ventas($f, $suc)->join('usuarios as u', 'u.usu_id', '=', 'v.usu_id');
        if (! empty($f['usu_id'])) {
            $q->where('v.usu_id', (int) $f['usu_id']);
        }

        return $q;
    }

    private static function r_usuario_rendimiento(array $f, ?int $suc): array
    {
        [$d, $h] = self::rango($f);
        $ventas = self::ventas($f, $suc)->selectRaw('v.usu_id, count(*) as n, sum(v.vta_total) as total')->groupBy('v.usu_id')->get()->keyBy('usu_id');
        $items = self::ventas($f, $suc)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')->selectRaw('v.usu_id, sum(d.det_cantidad) as u')->groupBy('v.usu_id')->pluck('u', 'usu_id');
        $anul = self::ventas($f, $suc, true)->selectRaw('v.usu_id, count(*) as n')->groupBy('v.usu_id')->pluck('n', 'usu_id');
        $devs = DB::table('devoluciones as dv')->join('ventas as v', 'v.vta_id', '=', 'dv.vta_id')->whereBetween('dv.dev_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('v.suc_id', $suc))
            ->selectRaw('v.usu_id, sum(dv.dev_total) as t')->groupBy('v.usu_id')->pluck('t', 'usu_id');
        $cobros = DB::table('cobranzas as cb')->where('cb.cob_estado', 'ACTIVA')->whereBetween('cb.cob_fecha', [$d, $h])->when($suc, fn ($q) => $q->where('cb.suc_id', $suc))
            ->selectRaw('cb.usu_id, sum(cb.cob_monto_total) as t')->groupBy('cb.usu_id')->pluck('t', 'usu_id');
        $difs = self::sesiones($f, $suc, false)->where('s.ses_estado', 'CERRADA')->selectRaw('s.usu_id, sum(s.ses_diferencia_gs) as t')->groupBy('s.usu_id')->pluck('t', 'usu_id');

        $ids = collect([$ventas->keys(), $anul->keys(), $cobros->keys(), $difs->keys()])->flatten()->unique()->values();
        $nombres = DB::table('usuarios')->whereIn('usu_id', $ids)->get()->keyBy('usu_id');
        $filas = [];
        foreach ($ids as $id) {
            $v = $ventas[$id] ?? null;
            $n = (int) ($v->n ?? 0);
            $na = (int) ($anul[$id] ?? 0);
            $filas[] = [self::nombre($nombres[$id]->usu_nombre ?? '?', $nombres[$id]->usu_apellido ?? ''), $n, round($v->total ?? 0), $n ? round($v->total / $n) : 0, $n ? round(($items[$id] ?? 0) / $n, 2) : 0,
                $na, self::pct($na, $n + $na), round($devs[$id] ?? 0), round($cobros[$id] ?? 0), round($difs[$id] ?? 0)];
        }
        usort($filas, fn ($a, $b) => $b[2] <=> $a[2]);

        return ['columnas' => [self::c('Usuario'), self::c('Ventas', 'entero', true), self::c('Total vendido Gs.', 'dinero', true), self::c('Ticket promedio', 'dinero'), self::c('Unidades por venta', 'cantidad'),
            self::c('Anuladas', 'entero', true), self::c('% anuladas', 'porc'), self::c('Devoluciones Gs.', 'dinero', true), self::c('Cobranzas Gs.', 'dinero', true), self::c('Dif. de caja Gs.', 'dinero', true)], 'filas' => $filas];
    }

    private static function r_usuario_dia(array $f, ?int $suc): array
    {
        $k = self::expr('dia', 'v.vta_fecha');
        $rows = self::ventasUsuario($f, $suc)->selectRaw("u.usu_nombre, u.usu_apellido, $k as k, count(*) as n, sum(v.vta_total) as total")
            ->groupBy('u.usu_id', 'u.usu_nombre', 'u.usu_apellido')->groupByRaw($k)->orderBy('u.usu_nombre')->orderByRaw($k)->get();

        return ['columnas' => [self::c('Usuario'), self::c('Día'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true), self::c('Ticket promedio', 'dinero')],
            'filas' => $rows->map(fn ($r) => [self::nombre($r->usu_nombre, $r->usu_apellido), Carbon::parse($r->k)->locale('es')->translatedFormat('D d/m/Y'), (int) $r->n, round($r->total), round($r->total / max($r->n, 1))])->all()];
    }

    private static function r_usuario_formapago(array $f, ?int $suc): array
    {
        $rows = self::ventasUsuario($f, $suc)->selectRaw("u.usu_nombre, u.usu_apellido, coalesce(v.vta_formapago,'—') as fp, count(*) as n, sum(v.vta_total) as total")
            ->groupBy('u.usu_id', 'u.usu_nombre', 'u.usu_apellido', 'v.vta_formapago')->orderBy('u.usu_nombre')->orderByDesc('total')->get();

        return ['columnas' => [self::c('Usuario'), self::c('Forma de pago'), self::c('Ventas', 'entero', true), self::c('Total Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::nombre($r->usu_nombre, $r->usu_apellido), $r->fp, (int) $r->n, round($r->total)])->all()];
    }

    private static function r_usuario_productos(array $f, ?int $suc): array
    {
        $rows = self::ventasUsuario($f, $suc)->join('detalle_ventas as d', 'd.vta_id', '=', 'v.vta_id')->join('productos as p', 'p.pro_id', '=', 'd.pro_id')
            ->selectRaw('u.usu_nombre, u.usu_apellido, p.pro_codigo, p.pro_nombre, sum(d.det_cantidad) as q, sum(d.det_subtotal) as ing')
            ->groupBy('u.usu_id', 'u.usu_nombre', 'u.usu_apellido', 'p.pro_id', 'p.pro_codigo', 'p.pro_nombre')->orderBy('u.usu_nombre')->orderByDesc('ing')->limit(300)->get();

        return ['columnas' => [self::c('Usuario'), self::c('Código'), self::c('Producto'), self::c('Unidades', 'cantidad', true), self::c('Ingreso Gs.', 'dinero', true)],
            'filas' => $rows->map(fn ($r) => [self::nombre($r->usu_nombre, $r->usu_apellido), $r->pro_codigo, $r->pro_nombre, (float) $r->q, round($r->ing)])->all()];
    }
}
