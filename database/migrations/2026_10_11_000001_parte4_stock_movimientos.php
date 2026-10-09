<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 4: historial de movimientos de stock.
 *
 *  - Crea la tabla stock_movimientos (cada entrada o salida de mercadería, con quién y por qué).
 *  - Toma una "foto" del stock actual de cada producto como SALDO_INICIAL, para que desde hoy
 *    la suma de los movimientos de un producto coincida con su stock.
 *  - Crea un índice único de código de producto, si no hay códigos repetidos.
 *
 * Es seguro correrla más de una vez. No modifica el stock ni borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movimientos')) {
            Schema::create('stock_movimientos', function (Blueprint $t) {
                $t->increments('smo_id');
                $t->unsignedBigInteger('pro_id');
                // SALDO_INICIAL, CARGA_INICIAL, VENTA, ANULACION_VENTA, DEVOLUCION,
                // INGRESO_MERCADERIA, AJUSTE_ENTRADA, AJUSTE_SALIDA, CONTEO
                $t->string('smo_tipo', 30);
                $t->decimal('smo_cantidad', 18, 2);          // con signo: entrada (+) o salida (-)
                $t->decimal('smo_stock_resultante', 18, 2);   // stock del producto después del movimiento
                $t->string('smo_motivo', 255)->nullable();
                $t->string('smo_referencia', 40)->nullable(); // por ejemplo "venta:57"
                $t->unsignedBigInteger('usu_id')->nullable();
                $t->timestamp('smo_fecha')->useCurrent();
                $t->index(['pro_id', 'smo_id']);
                $t->index('smo_fecha');
                $t->index('smo_tipo');
            });
        }

        // Foto inicial: solo para productos que todavía no tienen ningún movimiento.
        if (Schema::hasTable('productos')) {
            DB::statement(
                "INSERT INTO stock_movimientos (pro_id, smo_tipo, smo_cantidad, smo_stock_resultante, smo_motivo, smo_fecha)
                 SELECT p.pro_id, 'SALDO_INICIAL', COALESCE(p.pro_stockactual, 0), COALESCE(p.pro_stockactual, 0),
                        'Saldo al activar el control de stock', CURRENT_TIMESTAMP
                 FROM productos p
                 WHERE NOT EXISTS (SELECT 1 FROM stock_movimientos m WHERE m.pro_id = p.pro_id)"
            );
        }

        // Código de producto único (si hoy hay repetidos no se crea; "sistema:verificar" lo avisa).
        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'pro_codigo')) {
            $repetidos = DB::table('productos')->whereNotNull('pro_codigo')
                ->select('pro_codigo')->groupBy('pro_codigo')->havingRaw('COUNT(*) > 1')->count();

            if ($repetidos === 0) {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_productos_codigo ON productos (pro_codigo) WHERE pro_codigo IS NOT NULL');
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_productos_codigo');
        // La tabla stock_movimientos no se borra al volver atrás, para no perder historial.
    }
};
