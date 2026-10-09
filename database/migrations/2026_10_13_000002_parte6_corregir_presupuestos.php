<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección de la Parte 6 para bases que ya traían una tabla `presupuestos` vieja
 * (columnas pre_validez_hasta, estados PENDIENTE/CONVERTIDO/ANULADA): la migración original la dejaba como estaba
 * y la pantalla de presupuestos fallaba con "no existe la columna pre_fecha_vencimiento".
 * Si la tabla vieja está vacía se reemplaza; si tiene datos se la aparta como `presupuestos_antiguo` sin borrarlos.
 * No toca nada si la tabla ya tiene la estructura nueva. Es seguro correrla más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presupuestos') && Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento')) {
            return;
        }

        if (Schema::hasTable('presupuestos')) {
            if (DB::table('presupuestos')->count() === 0) {
                Schema::drop('presupuestos');
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE presupuestos RENAME TO presupuestos_antiguo');
                DB::statement('ALTER INDEX IF EXISTS presupuestos_pkey RENAME TO presupuestos_antiguo_pkey');
                DB::statement('ALTER SEQUENCE IF EXISTS presupuestos_pre_id_seq RENAME TO presupuestos_antiguo_pre_id_seq');
            } else {
                throw new RuntimeException('La tabla presupuestos existente tiene datos y otra estructura; resolvelo a mano antes de migrar.');
            }
        }

        if (! Schema::hasTable('presupuestos')) {
            Schema::create('presupuestos', function (Blueprint $t) {
                $t->increments('pre_id');
                $t->unsignedBigInteger('cli_id')->nullable();          // cliente registrado (opcional)
                $t->string('pre_cliente_nombre', 120)->nullable();      // o un nombre libre, para quien aún no es cliente
                $t->unsignedBigInteger('usu_id');
                $t->unsignedBigInteger('suc_id')->nullable();
                $t->timestamp('pre_fecha')->useCurrent();
                $t->unsignedSmallInteger('pre_validez_dias');
                $t->date('pre_fecha_vencimiento');
                $t->decimal('pre_total', 18, 2);
                $t->string('pre_estado', 12)->default('BORRADOR');     // BORRADOR | ENVIADO | ACEPTADO | RECHAZADO | FACTURADO
                $t->string('pre_observacion', 255)->nullable();
                $t->string('pre_condiciones', 500)->nullable();         // forma de pago, entrega, garantía...
                $t->timestamp('pre_aceptado_fecha')->nullable();
                $t->string('pre_rechazo_motivo', 255)->nullable();
                $t->unsignedBigInteger('vta_id')->nullable();           // venta en la que se convirtió
                $t->timestamp('pre_facturado_fecha')->nullable();
                $t->index(['pre_estado', 'pre_fecha_vencimiento']);
                $t->index('cli_id');
                $t->index('vta_id');
            });
        }

    }

    public function down(): void
    {
        // A propósito vacío: no se borran presupuestos al deshacer migraciones.
    }
};
