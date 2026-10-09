<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 6: presupuestos (cotizaciones a clientes).
 * Crea tablas nuevas; si encuentra la tabla `presupuestos` vieja y vacía la reemplaza, y si tiene datos la aparta sin borrarlos. Es seguro correrla más de una vez.
 * Un presupuesto NO mueve stock ni caja: eso recién pasa cuando se convierte en venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->apartarTablaVieja();

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

        if (! Schema::hasTable('detalle_presupuestos')) {
            Schema::create('detalle_presupuestos', function (Blueprint $t) {
                $t->increments('dpr_id');
                $t->unsignedBigInteger('pre_id');
                $t->unsignedBigInteger('pro_id');
                $t->decimal('dpr_cantidad', 18, 2);
                $t->decimal('dpr_precio', 18, 2);      // precio unitario cotizado
                $t->decimal('dpr_subtotal', 18, 2);
                $t->index('pre_id');
                $t->index('pro_id');
            });
        }
    }

    /**
     * Algunas bases traen una tabla `presupuestos` vieja (columnas pre_validez_hasta, estados PENDIENTE/CONVERTIDO/ANULADA)
     * que ninguna pantalla usaba. Si está vacía se reemplaza; si tiene datos se la aparta como `presupuestos_antiguo`.
     */
    private function apartarTablaVieja(): void
    {
        if (! Schema::hasTable('presupuestos') || Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento')) {
            return;
        }

        if (DB::table('presupuestos')->count() === 0) {
            Schema::drop('presupuestos');

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('La tabla presupuestos existente tiene datos y otra estructura; resolvelo a mano antes de migrar.');
        }

        DB::statement('ALTER TABLE presupuestos RENAME TO presupuestos_antiguo');
        DB::statement('ALTER INDEX IF EXISTS presupuestos_pkey RENAME TO presupuestos_antiguo_pkey');
        DB::statement('ALTER SEQUENCE IF EXISTS presupuestos_pre_id_seq RENAME TO presupuestos_antiguo_pre_id_seq');
    }

    public function down(): void
    {
        // A propósito vacío: no se borran presupuestos al deshacer migraciones.
    }
};
