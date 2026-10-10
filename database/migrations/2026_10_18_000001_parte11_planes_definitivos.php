<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga los planes y adicionales definitivos que se muestran en la página pública:
 * Inicio, Negocio, Crecimiento, Empresa y "A medida" (a consultar).
 *
 * - Si ya existen los planes de ejemplo (Estándar, Profesional, Corporativo) se reutilizan (mismo id),
 *   así no se pierde ninguna referencia: Estándar pasa a Negocio, Profesional a Crecimiento y Corporativo a "A medida".
 * - Es idempotente: se puede ejecutar más de una vez sin duplicar nada.
 * - Los precios y textos se siguen pudiendo cambiar desde el panel del vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planes') || ! Schema::hasColumn('planes', 'plan_publico')) {
            return;
        }

        $planes = [
            ['plan_nombre' => 'Inicio', 'plan_descripcion' => 'Para arrancar o trabajar solo.', 'plan_edicion' => 'BASICA', 'plan_precio' => 150000, 'plan_max_sucursales' => 1, 'plan_max_cajas' => 1, 'plan_max_usuarios' => 1, 'plan_destacado' => false, 'plan_orden' => 1, 'plan_etiqueta' => null, 'plan_boton' => 'Probar este plan',
                'plan_caracteristicas' => "Punto de venta, contado y crédito\nCaja con apertura y cierre\nClientes, productos y cobranzas\nPresupuestos con fecha de validez\nInventario y ajustes de stock\n-Compras y proveedores\n-Contabilidad integrada"],
            ['plan_nombre' => 'Negocio', 'plan_descripcion' => 'Para un local con equipo.', 'plan_edicion' => 'BASICA', 'plan_precio' => 220000, 'plan_max_sucursales' => 1, 'plan_max_cajas' => 2, 'plan_max_usuarios' => 3, 'plan_destacado' => false, 'plan_orden' => 2, 'plan_etiqueta' => null, 'plan_boton' => 'Probar este plan',
                'plan_caracteristicas' => "Todo lo del plan Inicio\nDos cajas para atender al mismo tiempo\nHasta 3 usuarios con permisos por rol\n-Compras y proveedores\n-Contabilidad integrada"],
            ['plan_nombre' => 'Crecimiento', 'plan_descripcion' => 'Para abrir tu segunda sucursal y crecer con control.', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 350000, 'plan_max_sucursales' => 2, 'plan_max_cajas' => 4, 'plan_max_usuarios' => 6, 'plan_destacado' => true, 'plan_orden' => 3, 'plan_etiqueta' => 'Recomendado', 'plan_boton' => 'Probar este plan',
                'plan_caracteristicas' => "Todo lo del plan Negocio\nHasta 2 sucursales\nCompras, proveedores y cuentas a pagar\nPromociones y descuentos con fechas\nReportes de rentabilidad y curva ABC\nVentas en guaraníes, dólares y reales\nContabilidad: asientos automáticos, balances y libro IVA"],
            ['plan_nombre' => 'Empresa', 'plan_descripcion' => 'Para varias sucursales y equipos grandes.', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 480000, 'plan_max_sucursales' => 3, 'plan_max_cajas' => 7, 'plan_max_usuarios' => 10, 'plan_destacado' => false, 'plan_orden' => 4, 'plan_etiqueta' => null, 'plan_boton' => 'Probar este plan',
                'plan_caracteristicas' => "Todo lo del plan Crecimiento\nHasta 3 sucursales con control por sucursal\nHasta 7 cajas y 10 usuarios\nTodo el equipo trabajando al mismo tiempo"],
            // Sin precio y con límites en 0 = plan a consultar; la página lo muestra como una franja "A medida".
            ['plan_nombre' => 'A medida', 'plan_descripcion' => 'Para más de 3 sucursales o necesidades propias.', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 0, 'plan_max_sucursales' => 0, 'plan_max_cajas' => 0, 'plan_max_usuarios' => 0, 'plan_destacado' => false, 'plan_orden' => 5, 'plan_etiqueta' => null, 'plan_boton' => 'Consultar',
                'plan_caracteristicas' => "Todo lo del plan Empresa\nSucursales, cajas y usuarios según tu necesidad\nSe arma con los adicionales de usuario, caja y sucursal"],
        ];

        // Plan de ejemplo que se reutiliza para cada nombre nuevo (conserva el id).
        $reutiliza = ['Negocio' => 'Estándar', 'Crecimiento' => 'Profesional', 'A medida' => 'Corporativo'];

        foreach ($planes as $p) {
            $fila = DB::table('planes')->where('plan_nombre', $p['plan_nombre'])->first();
            if (! $fila && isset($reutiliza[$p['plan_nombre']])) {
                $fila = DB::table('planes')->where('plan_nombre', $reutiliza[$p['plan_nombre']])->first();
            }

            $datos = $p + ['plan_meses' => 1, 'plan_activo' => true, 'plan_publico' => true];

            if ($fila) {
                DB::table('planes')->where('plan_id', $fila->plan_id)->update($datos);
            } else {
                DB::table('planes')->insert($datos);
            }
        }

        // Adicionales: usuario, caja y sucursal.
        if (Schema::hasTable('plan_adicionales')) {
            $adicionales = [
                ['Usuario adicional', 'Usuario adicional', 25000, 1],
                ['Caja adicional', 'Punto de venta adicional', 45000, 2],
                ['Sucursal adicional', 'Sucursal adicional', 89000, 3],
            ];
            foreach ($adicionales as [$nombre, $anterior, $precio, $orden]) {
                $fila = DB::table('plan_adicionales')->where('ada_nombre', $nombre)->first()
                    ?? DB::table('plan_adicionales')->where('ada_nombre', $anterior)->first();
                $datos = ['ada_nombre' => $nombre, 'ada_precio' => $precio, 'ada_periodo' => 'mes', 'ada_orden' => $orden, 'ada_activo' => true];
                if ($fila) {
                    DB::table('plan_adicionales')->where('ada_id', $fila->ada_id)->update($datos);
                } else {
                    DB::table('plan_adicionales')->insert($datos);
                }
            }
        }
    }

    public function down(): void
    {
        // A propósito vacío: no se borran planes ni precios al deshacer migraciones.
    }
};
