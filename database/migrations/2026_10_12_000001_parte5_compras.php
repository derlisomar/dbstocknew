<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 5: compras a proveedores, cuentas a pagar y pagos.
 * Crea tablas nuevas y una columna nueva en caja_movimientos. No modifica ni borra datos existentes.
 * Es seguro correrla más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras')) {
            Schema::create('compras', function (Blueprint $t) {
                $t->increments('com_id');
                $t->unsignedBigInteger('prov_id');
                $t->unsignedBigInteger('usu_id');
                $t->unsignedBigInteger('suc_id')->nullable();
                $t->timestamp('com_fecha')->useCurrent();
                $t->string('com_nro_documento', 40)->nullable();    // factura o remisión del proveedor
                $t->string('com_tipo', 10);                          // CONTADO | CREDITO
                $t->decimal('com_total', 18, 2);
                $t->string('com_estado', 12)->default('REGISTRADA'); // REGISTRADA | ANULADA
                $t->string('com_observacion', 255)->nullable();
                $t->unsignedBigInteger('com_anulada_por')->nullable();
                $t->timestamp('com_anulada_fecha')->nullable();
                $t->string('com_motivo_anulacion', 255)->nullable();
                $t->index(['prov_id', 'com_nro_documento']);
                $t->index('com_fecha');
                $t->index('com_estado');
            });
        }

        if (! Schema::hasTable('detalle_compras')) {
            Schema::create('detalle_compras', function (Blueprint $t) {
                $t->increments('dco_id');
                $t->unsignedBigInteger('com_id');
                $t->unsignedBigInteger('pro_id');
                $t->decimal('dco_cantidad', 18, 2);
                $t->decimal('dco_costo', 18, 2);       // costo unitario de esta compra
                $t->decimal('dco_subtotal', 18, 2);
                $t->decimal('dco_devuelta', 18, 2)->default(0); // cantidad ya devuelta al proveedor
                $t->index('com_id');
                $t->index('pro_id');
            });
        }

        if (! Schema::hasTable('cuentas_pagar')) {
            Schema::create('cuentas_pagar', function (Blueprint $t) {
                $t->increments('cpa_id');
                $t->unsignedBigInteger('com_id');
                $t->unsignedBigInteger('prov_id');
                $t->decimal('cpa_monto_total', 18, 2);
                $t->decimal('cpa_saldo_pendiente', 18, 2);
                $t->date('cpa_fecha_vencimiento')->nullable();
                $t->string('cpa_estado', 12)->default('PENDIENTE'); // PENDIENTE | PAGADA | ANULADA
                $t->index('prov_id');
                $t->index('cpa_estado');
                $t->index('cpa_fecha_vencimiento');
            });
        }

        if (! Schema::hasTable('pagos_proveedores')) {
            Schema::create('pagos_proveedores', function (Blueprint $t) {
                $t->increments('pag_id');
                $t->unsignedBigInteger('cpa_id');
                $t->unsignedBigInteger('prov_id');
                $t->unsignedBigInteger('usu_id');
                $t->unsignedBigInteger('ses_id')->nullable();   // solo si salió efectivo de una caja
                $t->timestamp('pag_fecha')->useCurrent();
                $t->decimal('pag_monto', 18, 2);
                $t->string('pag_forma_pago', 20);               // EFECTIVO | TRANSFERENCIA | CHEQUE | TARJETA | OTRO
                $t->string('pag_referencia', 120)->nullable();  // nro. de transferencia, cheque, recibo
                $t->string('pag_estado', 10)->default('ACTIVO'); // ACTIVO | ANULADO
                $t->unsignedBigInteger('pag_anulado_por')->nullable();
                $t->timestamp('pag_anulado_fecha')->nullable();
                $t->string('pag_motivo_anulacion', 255)->nullable();
                $t->index('cpa_id');
                $t->index('prov_id');
                $t->index('pag_fecha');
            });
        }

        // Vínculo del libro de caja con el pago a proveedor (para poder anularlo después).
        if (Schema::hasTable('caja_movimientos') && ! Schema::hasColumn('caja_movimientos', 'pag_id')) {
            Schema::table('caja_movimientos', function (Blueprint $t) {
                $t->unsignedBigInteger('pag_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Las tablas de compras no se borran al volver atrás, para no perder historial.
    }
};
