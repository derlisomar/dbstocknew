<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 10: módulo contable. Crea tablas nuevas y agrega UNA columna opcional a compras (timbrado del proveedor).
 * Es seguro correrla más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cont_cuentas')) {
            Schema::create('cont_cuentas', function (Blueprint $t) {
                $t->increments('cue_id');
                $t->string('cue_codigo', 20)->unique();
                $t->string('cue_nombre', 120);
                $t->string('cue_tipo', 12);                 // ACTIVO | PASIVO | PATRIMONIO | INGRESO | EGRESO
                $t->boolean('cue_imputable')->default(true); // false = cuenta "título" (agrupa, no recibe asientos)
                $t->boolean('cue_activa')->default(true);
                $t->string('cue_clave', 40)->nullable()->unique(); // identifica las cuentas que usa el motor
            });
        }

        if (! Schema::hasTable('cont_asientos')) {
            Schema::create('cont_asientos', function (Blueprint $t) {
                $t->increments('asi_id');
                $t->unsignedInteger('asi_numero')->unique();
                $t->date('asi_fecha');
                $t->string('asi_glosa', 255);
                $t->string('asi_origen', 24);               // VENTA, COSTO_VENTA, COBRANZA, COMPRA, ..., MANUAL
                $t->string('asi_clave', 60)->nullable()->unique(); // "VENTA:15": evita contabilizar dos veces
                $t->string('asi_estado', 10)->default('VIGENTE');  // VIGENTE | ANULADO
                $t->unsignedBigInteger('usu_id')->nullable();
                $t->unsignedBigInteger('asi_revierte_id')->nullable();
                $t->timestamp('asi_creado')->useCurrent();
                $t->index('asi_fecha');
                $t->index('asi_origen');
            });
        }

        if (! Schema::hasTable('cont_lineas')) {
            Schema::create('cont_lineas', function (Blueprint $t) {
                $t->increments('lin_id');
                $t->unsignedInteger('asi_id');
                $t->unsignedInteger('cue_id');
                $t->decimal('lin_debe', 18, 2)->default(0);
                $t->decimal('lin_haber', 18, 2)->default(0);
                $t->string('lin_detalle', 160)->nullable();
                $t->index('asi_id');
                $t->index('cue_id');
            });
        }

        // Timbrado de la factura del proveedor (para el libro de IVA compras).
        if (Schema::hasTable('compras') && ! Schema::hasColumn('compras', 'com_timbrado')) {
            Schema::table('compras', fn (Blueprint $t) => $t->string('com_timbrado', 20)->nullable());
        }

        if (! Schema::hasTable('cont_mapeos')) {
            Schema::create('cont_mapeos', function (Blueprint $t) {
                $t->increments('map_id');
                $t->string('map_tipo', 20);                 // PAGO | CAT_INGRESO | CAT_INVENTARIO | CAT_COSTO
                $t->string('map_clave', 40);                // EFECTIVO_GS, TRANSFERENCIA, ... o el id de la categoría
                $t->unsignedInteger('cue_id');
                $t->unique(['map_tipo', 'map_clave']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cont_mapeos');
        Schema::dropIfExists('cont_lineas');
        Schema::dropIfExists('cont_asientos');
        Schema::dropIfExists('cont_cuentas');
    }
};
