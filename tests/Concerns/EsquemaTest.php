<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas mínimas (SQLite en memoria) para las pruebas.
 * Primero se crean las tablas "de antes" y después se corren las migraciones REALES de la Parte 2 y la Parte 3,
 * así las pruebas verifican también que esa migración funciona.
 */
trait EsquemaTest
{
    protected function crearTablas(): void
    {
        $this->crearTablasBase();

        $migracion = require database_path('migrations/2026_10_09_000001_parte2_caja_ventas_auditoria.php');
        $migracion->up();

        $migracion3 = require database_path('migrations/2026_10_10_000001_parte3_cobros_auditoria.php');
        $migracion3->up();

        $migracion4 = require database_path('migrations/2026_10_11_000001_parte4_stock_movimientos.php');
        $migracion4->up();

        $migracion5 = require database_path('migrations/2026_10_12_000001_parte5_compras.php');
        $migracion5->up();

        $migracion6 = require database_path('migrations/2026_10_13_000001_parte6_presupuestos.php');
        $migracion6->up();

        $migracion8 = require database_path('migrations/2026_10_14_000001_parte8_panel_vendedor.php');
        $migracion8->up();
    }

    /** En PostgreSQL, tras insertar ids a mano hay que avanzar las secuencias (SQLite no lo necesita). */
    protected function ajustarSecuencias(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['clientes' => 'cli_id', 'productos' => 'pro_id', 'cajas' => 'caj_id', 'sucursales' => 'suc_id'] as $tabla => $pk) {
            \Illuminate\Support\Facades\DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', '{$pk}'), 1000)");
        }
    }

    protected function crearTablasBase(): void
    {
        Schema::create('roles', fn (Blueprint $t) => [$t->increments('rol_id'), $t->string('rol_nombre'), $t->string('rol_descripcion')->nullable()]);
        Schema::create('permisos', fn (Blueprint $t) => [$t->increments('perm_id'), $t->string('perm_codigo'), $t->string('perm_descripcion')->nullable(), $t->string('perm_modulo')->nullable()]);
        Schema::create('rol_permisos', fn (Blueprint $t) => [$t->unsignedInteger('rol_id'), $t->unsignedInteger('perm_id')]);
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('usu_id'); $t->unsignedInteger('rol_id')->nullable();
            $t->string('usu_cedula')->nullable(); $t->string('usu_nombre')->nullable(); $t->string('usu_apellido')->nullable();
            $t->string('usu_usuario'); $t->string('usu_email')->nullable(); $t->string('usu_password');
            $t->boolean('usu_activo')->default(true); $t->string('remember_token')->nullable();
        });
        Schema::create('sucursales', function (Blueprint $t) {
            $t->increments('suc_id'); $t->string('suc_nombre'); $t->string('suc_direccion')->nullable(); $t->string('suc_telefono')->nullable();
            $t->boolean('suc_activa')->default(true); $t->string('suc_actividad_economica')->nullable(); $t->string('suc_timbrado')->nullable();
            $t->string('suc_est_punto_exp')->nullable(); $t->date('suc_timbrado_inicio')->nullable(); $t->date('suc_timbrado_fin')->nullable();
            $t->integer('suc_factura_secuencia')->default(1);
        });
        Schema::create('cajas', function (Blueprint $t) {
            $t->increments('caj_id'); $t->unsignedInteger('suc_id'); $t->string('caj_nombre');
            $t->decimal('caj_saldo_gs', 18, 2)->default(0); $t->decimal('caj_saldo_usd', 18, 2)->default(0); $t->decimal('caj_saldo_brl', 18, 2)->default(0);
            $t->boolean('caj_activa')->default(true); $t->string('caj_impresora')->nullable(); $t->string('caj_tipo_impresion')->nullable();
        });
        Schema::create('caja_sesiones', function (Blueprint $t) {
            $t->increments('ses_id'); $t->unsignedInteger('caj_id'); $t->unsignedInteger('usu_id'); $t->string('ses_estado');
            $t->dateTime('ses_fecha_apertura')->nullable(); $t->dateTime('ses_fecha_cierre')->nullable();
            $t->decimal('ses_monto_inicial_gs', 18, 2)->default(0); $t->decimal('ses_monto_inicial_usd', 18, 2)->default(0); $t->decimal('ses_monto_inicial_brl', 18, 2)->default(0);
            $t->decimal('ses_monto_cierre_gs', 18, 2)->nullable(); $t->decimal('ses_monto_cierre_usd', 18, 2)->nullable(); $t->decimal('ses_monto_cierre_brl', 18, 2)->nullable();
        });
        Schema::create('clientes', function (Blueprint $t) {
            $t->increments('cli_id'); $t->string('cli_nombre'); $t->string('cli_apellido')->nullable(); $t->string('cli_ruc_ci');
            $t->string('cli_telefono')->nullable(); $t->string('cli_direccion')->nullable(); $t->string('cli_email')->nullable();
            $t->decimal('cli_limite_credito', 18, 2)->default(0); $t->boolean('cli_es_mayorista')->default(false);
            $t->boolean('cli_permitir_credito')->default(false); $t->boolean('cli_bloqueado')->default(false);
        });
        Schema::create('productos', function (Blueprint $t) {
            $t->increments('pro_id'); $t->unsignedInteger('cat_id')->nullable(); $t->string('pro_nombre');
            $t->decimal('pro_precioventa', 18, 2); $t->decimal('pro_preciomayorista', 18, 2)->default(0); $t->decimal('pro_preciocosto', 18, 2)->default(0);
            $t->decimal('pro_stockactual', 18, 2)->default(0); $t->boolean('pro_activo')->default(true); $t->integer('pro_tipo_iva')->default(10);
            $t->string('pro_codigo')->nullable(); $t->string('pro_descripcion')->nullable(); $t->unsignedInteger('prov_id')->nullable();
            $t->unsignedInteger('suc_id')->nullable(); $t->unsignedInteger('dep_id')->nullable(); $t->decimal('pro_stockminimo', 18, 2)->default(0);
            $t->date('pro_fechavencimiento')->nullable(); $t->string('pro_imagen')->nullable();
        });
        Schema::create('categorias', fn (Blueprint $t) => [$t->increments('cat_id'), $t->string('cat_nombre')]);
        Schema::create('proveedores', fn (Blueprint $t) => [$t->increments('prov_id'), $t->string('prov_razonsocial'), $t->string('prov_ruc')->nullable()]);
        Schema::create('depositos', fn (Blueprint $t) => [$t->increments('dep_id'), $t->unsignedInteger('suc_id')->nullable(), $t->string('dep_nombre'), $t->string('dep_descripcion')->nullable(), $t->boolean('dep_activo')->default(true)]);
        Schema::create('promociones', function (Blueprint $t) {
            $t->increments('prom_id'); $t->string('prom_nombre'); $t->string('prom_tipo_descuento'); $t->decimal('prom_valor', 18, 2);
            $t->date('prom_fecha_inicio')->nullable(); $t->date('prom_fecha_fin'); $t->string('prom_aplica_a');
            $t->unsignedInteger('cat_id')->nullable(); $t->unsignedInteger('pro_id')->nullable(); $t->boolean('prom_activa')->default(true);
        });
        Schema::create('cotizaciones', function (Blueprint $t) {
            $t->increments('cot_id'); $t->date('cot_fecha')->nullable(); $t->decimal('cot_dolar', 18, 2); $t->decimal('cot_real', 18, 2); $t->boolean('cot_activa')->default(true);
        });
        Schema::create('ventas', function (Blueprint $t) {
            $t->increments('vta_id'); $t->unsignedInteger('suc_id'); $t->unsignedInteger('cli_id'); $t->unsignedInteger('usu_id'); $t->unsignedInteger('ses_id');
            $t->dateTime('vta_fecha')->nullable();
            $t->string('vta_tipo'); $t->string('vta_formapago')->nullable(); $t->string('nro_transferencia')->nullable(); $t->string('vta_moneda')->nullable();
            $t->decimal('vta_total', 18, 2); $t->string('vta_timbrado')->nullable(); $t->string('vta_nro_factura')->nullable(); $t->string('vta_estado');
            $t->decimal('vta_total_exenta', 18, 2)->default(0); $t->decimal('vta_total_iva5', 18, 2)->default(0); $t->decimal('vta_total_iva10', 18, 2)->default(0);
        });
        Schema::create('detalle_ventas', function (Blueprint $t) {
            $t->increments('det_vta_id'); $t->unsignedInteger('vta_id'); $t->unsignedInteger('pro_id'); $t->decimal('det_cantidad', 18, 2);
            $t->decimal('det_preciounitario', 18, 2); $t->decimal('det_subtotal', 18, 2); $t->decimal('det_preciocosto', 18, 2)->default(0);
        });
        Schema::create('caja_movimientos', function (Blueprint $t) {
            $t->increments('mov_id'); $t->unsignedInteger('ses_id'); $t->string('mov_tipo'); $t->decimal('mov_monto', 18, 2);
            $t->string('mov_concepto')->nullable(); $t->string('mov_moneda')->nullable(); $t->unsignedInteger('caj_id_destino')->nullable();
        });
        Schema::create('cuentas_cobrar', function (Blueprint $t) {
            $t->increments('cred_id'); $t->unsignedInteger('vta_id'); $t->unsignedInteger('cli_id'); $t->decimal('cred_monto_total', 18, 2);
            $t->decimal('cred_saldo_pendiente', 18, 2); $t->dateTime('cred_fecha_vencimiento')->nullable(); $t->string('cred_estado');
        });
        Schema::create('cobranzas', function (Blueprint $t) {
            $t->increments('cob_id'); $t->unsignedInteger('suc_id')->nullable(); $t->unsignedInteger('cli_id'); $t->unsignedInteger('usu_id'); $t->unsignedInteger('ses_id');
            $t->dateTime('cob_fecha')->nullable(); $t->decimal('cob_monto_total', 18, 2); $t->string('cob_estado');
        });
        Schema::create('detalle_cobranzas', function (Blueprint $t) {
            $t->increments('det_cob_id'); $t->unsignedInteger('cob_id'); $t->unsignedInteger('cred_id'); $t->decimal('det_monto_pagado', 18, 2);
        });
        Schema::create('ingresos_egresos', function (Blueprint $t) {
            $t->increments('ie_id'); $t->unsignedInteger('ses_id'); $t->string('ie_tipo'); $t->decimal('ie_monto', 18, 2);
            $t->string('ie_concepto')->nullable(); $t->dateTime('ie_fecha')->nullable();
        });
    }
}
