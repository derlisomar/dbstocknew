<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 11: planes que se muestran en la página pública (editables desde el panel del vendedor)
 * y adicionales ("capacidad flexible"). Todo queda oculto hasta que el vendedor lo publique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planes')) {
            Schema::table('planes', function (Blueprint $t) {
                if (! Schema::hasColumn('planes', 'plan_publico')) {
                    $t->boolean('plan_publico')->default(false);
                }
                if (! Schema::hasColumn('planes', 'plan_destacado')) {
                    $t->boolean('plan_destacado')->default(false);
                }
                if (! Schema::hasColumn('planes', 'plan_orden')) {
                    $t->unsignedSmallInteger('plan_orden')->default(0);
                }
                if (! Schema::hasColumn('planes', 'plan_etiqueta')) {
                    $t->string('plan_etiqueta', 30)->nullable();
                }
                if (! Schema::hasColumn('planes', 'plan_boton')) {
                    $t->string('plan_boton', 40)->nullable();
                }
                if (! Schema::hasColumn('planes', 'plan_caracteristicas')) {
                    $t->text('plan_caracteristicas')->nullable();
                }
            });

            if (DB::table('planes')->whereIn('plan_nombre', ['Estándar', 'Profesional', 'Corporativo'])->count() === 0) {
                DB::table('planes')->insert([
                    ['plan_nombre' => 'Estándar', 'plan_descripcion' => 'Para el comercio que quiere vender rápido y cobrar sin vueltas.', 'plan_edicion' => 'BASICA', 'plan_precio' => 0, 'plan_meses' => 1, 'plan_max_sucursales' => 1, 'plan_max_cajas' => 2, 'plan_max_usuarios' => 3, 'plan_activo' => true, 'plan_publico' => false, 'plan_destacado' => false, 'plan_orden' => 1, 'plan_etiqueta' => null, 'plan_boton' => 'Probar este plan',
                        'plan_caracteristicas' => "Punto de venta, contado y crédito\nCajas con apertura y cierre\nClientes, productos y cobranzas\nPresupuestos con fecha de validez\nInventario y ajustes de stock\n-Compras y proveedores\n-Contabilidad integrada"],
                    ['plan_nombre' => 'Profesional', 'plan_descripcion' => 'Para negocios con compras, varias cajas y control fino.', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 0, 'plan_meses' => 1, 'plan_max_sucursales' => 3, 'plan_max_cajas' => 5, 'plan_max_usuarios' => 10, 'plan_activo' => true, 'plan_publico' => false, 'plan_destacado' => true, 'plan_orden' => 2, 'plan_etiqueta' => 'Recomendado', 'plan_boton' => 'Probar este plan',
                        'plan_caracteristicas' => "Todo lo del plan Estándar\nCompras, proveedores y cuentas a pagar\nPromociones y descuentos con fechas\nReportes de rentabilidad y curva ABC\nVentas en guaraníes, dólares y reales\nContabilidad: asientos automáticos, balances y libro IVA"],
                    ['plan_nombre' => 'Corporativo', 'plan_descripcion' => 'Para empresas con varias sucursales y necesidades propias.', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 0, 'plan_meses' => 1, 'plan_max_sucursales' => 0, 'plan_max_cajas' => 0, 'plan_max_usuarios' => 0, 'plan_activo' => true, 'plan_publico' => false, 'plan_destacado' => false, 'plan_orden' => 3, 'plan_etiqueta' => null, 'plan_boton' => 'Consultar',
                        'plan_caracteristicas' => "Todo lo del plan Profesional\nSucursales, cajas y usuarios sin límite\nRegistro de auditoría de cada acción\nAdaptaciones a medida\nSoporte prioritario"],
                ]);
            }
        }

        if (! Schema::hasTable('plan_adicionales')) {
            Schema::create('plan_adicionales', function (Blueprint $t) {
                $t->increments('ada_id');
                $t->string('ada_nombre', 80);
                $t->decimal('ada_precio', 18, 2)->default(0);
                $t->string('ada_periodo', 20)->default('mes');          // texto que va después de "/": mes, año, único
                $t->unsignedSmallInteger('ada_orden')->default(0);
                $t->boolean('ada_activo')->default(true);
            });

            DB::table('plan_adicionales')->insert([
                ['ada_nombre' => 'Usuario adicional', 'ada_precio' => 0, 'ada_periodo' => 'mes', 'ada_orden' => 1, 'ada_activo' => true],
                ['ada_nombre' => 'Punto de venta adicional', 'ada_precio' => 0, 'ada_periodo' => 'mes', 'ada_orden' => 2, 'ada_activo' => true],
                ['ada_nombre' => 'Sucursal adicional', 'ada_precio' => 0, 'ada_periodo' => 'mes', 'ada_orden' => 3, 'ada_activo' => true],
            ]);
        }

        // Textos de la sección de precios (se editan desde el panel del vendedor).
        if (Schema::hasTable('configuracion_sistema')) {
            foreach (['pub_planes_titulo' => 'Planes simples, para cada tamaño de negocio.', 'pub_planes_texto' => 'Elegí el que mejor se ajuste hoy y cambiá de plan cuando tu negocio crezca.'] as $k => $v) {
                if (! DB::table('configuracion_sistema')->where('cfg_clave', $k)->exists()) {
                    DB::table('configuracion_sistema')->insert(['cfg_clave' => $k, 'cfg_valor' => $v]);
                }
            }
        }
    }

    public function down(): void
    {
        // A propósito vacío: no se borran planes ni precios al deshacer migraciones.
    }
};
