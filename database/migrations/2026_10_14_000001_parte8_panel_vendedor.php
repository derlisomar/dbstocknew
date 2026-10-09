<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 8: panel del vendedor (configuración del negocio, ediciones, planes y licencia).
 * Solo crea tablas nuevas. Un sistema sin datos en ellas se comporta exactamente como antes
 * (edición completa, sin licencia que vencer). Es seguro correrla más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_sistema')) {
            Schema::create('configuracion_sistema', function (Blueprint $t) {
                $t->string('cfg_clave', 60)->primary();
                $t->text('cfg_valor')->nullable();
            });
        }

        if (! Schema::hasTable('planes')) {
            Schema::create('planes', function (Blueprint $t) {
                $t->increments('plan_id');
                $t->string('plan_nombre', 80);
                $t->string('plan_descripcion', 255)->nullable();
                $t->string('plan_edicion', 10)->default('COMPLETA');      // COMPLETA | BASICA
                $t->text('plan_modulos')->nullable();                      // módulos extra (JSON), además de los de la edición
                $t->decimal('plan_precio', 18, 2)->default(0);
                $t->unsignedSmallInteger('plan_meses')->default(1);        // cada cuántos meses se paga; 0 = pago único
                $t->unsignedInteger('plan_max_sucursales')->default(0);    // 0 = sin límite
                $t->unsignedInteger('plan_max_cajas')->default(0);
                $t->unsignedInteger('plan_max_usuarios')->default(0);
                $t->boolean('plan_activo')->default(true);
            });

            DB::table('planes')->insert([
                ['plan_nombre' => 'Básico', 'plan_descripcion' => 'Venta rápida, caja, cobranzas, presupuestos e inventario', 'plan_edicion' => 'BASICA', 'plan_precio' => 0, 'plan_meses' => 1, 'plan_max_sucursales' => 1, 'plan_max_cajas' => 2, 'plan_max_usuarios' => 3, 'plan_activo' => true],
                ['plan_nombre' => 'Completo', 'plan_descripcion' => 'Todos los módulos, sin límites', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 0, 'plan_meses' => 1, 'plan_max_sucursales' => 0, 'plan_max_cajas' => 0, 'plan_max_usuarios' => 0, 'plan_activo' => true],
            ]);
        }

        if (! Schema::hasTable('licencia_pagos')) {
            Schema::create('licencia_pagos', function (Blueprint $t) {
                $t->increments('lpa_id');
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->date('lpa_fecha');
                $t->decimal('lpa_monto', 18, 2);
                $t->string('lpa_forma', 30)->nullable();
                $t->string('lpa_referencia', 120)->nullable();
                $t->date('lpa_desde')->nullable();
                $t->date('lpa_hasta')->nullable();                         // hasta cuándo cubre este pago (null = pago único)
                $t->date('lpa_vence_anterior')->nullable();               // para poder deshacer
                $t->string('lpa_tipo_anterior', 12)->nullable();
                $t->string('lpa_nota', 255)->nullable();
                $t->string('lpa_estado', 10)->default('ACTIVO');           // ACTIVO | ANULADO
                $t->timestamp('lpa_creado')->useCurrent();
                $t->timestamp('lpa_anulado_fecha')->nullable();
                $t->string('lpa_motivo_anulacion', 255)->nullable();
                $t->index('lpa_estado');
            });
        }
    }

    public function down(): void
    {
        // A propósito vacío: no se borra la configuración ni los pagos al deshacer migraciones.
    }
};
