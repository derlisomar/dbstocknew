<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 14: unidades de medida, códigos de barra y cantidades con decimales.
 *  - productos.pro_unidad (UN, KG, LT) y productos.pro_plu (código de balanza)
 *  - tabla producto_codigos (varios códigos de barra por producto)
 *  - en PostgreSQL, las cantidades pasan a 3 decimales (el stock y lo vendido eran enteros)
 * Se puede correr más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function ($t) {
            if (! Schema::hasColumn('productos', 'pro_unidad')) {
                $t->string('pro_unidad', 2)->default('UN');
            }
            if (! Schema::hasColumn('productos', 'pro_plu')) {
                $t->string('pro_plu', 10)->nullable();
            }
        });

        if (! Schema::hasTable('producto_codigos')) {
            Schema::create('producto_codigos', function ($t) {
                $t->increments('cod_id');
                $t->integer('pro_id');
                $t->string('cod_codigo', 50)->unique();
                $t->index('pro_id');
                $t->foreign('pro_id')->references('pro_id')->on('productos')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DO \$\$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'productos_pro_unidad_check') THEN
                ALTER TABLE productos ADD CONSTRAINT productos_pro_unidad_check CHECK (pro_unidad IN ('UN','KG','LT'));
            END IF;
        END \$\$;");
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_productos_plu ON productos (pro_plu) WHERE pro_plu IS NOT NULL');

        // Cantidades con 3 decimales (solo si todavía no los tienen).
        foreach ([
            ['productos', 'pro_stockactual', 14, "0"],
            ['productos', 'pro_stockminimo', 14, "5"],
            ['detalle_ventas', 'det_cantidad', 14, null],
            ['stock_movimientos', 'smo_cantidad', 18, null],
            ['stock_movimientos', 'smo_stock_resultante', 18, null],
            ['detalle_compras', 'dco_cantidad', 18, null],
            ['detalle_compras', 'dco_devuelta', 18, "0"],
            ['detalle_presupuestos', 'dpr_cantidad', 18, null],
            ['detalle_devoluciones', 'ddv_cantidad', 18, null],
        ] as [$tabla, $col, $prec, $def]) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $col)) {
                continue;
            }
            $escala = DB::scalar('SELECT numeric_scale FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?', [$tabla, $col]);
            if ($escala !== null && (int) $escala >= 3) {
                continue;
            }
            DB::statement("ALTER TABLE {$tabla} ALTER COLUMN {$col} DROP DEFAULT");
            DB::statement("ALTER TABLE {$tabla} ALTER COLUMN {$col} TYPE numeric({$prec},3) USING {$col}::numeric");
            if ($def !== null) {
                DB::statement("ALTER TABLE {$tabla} ALTER COLUMN {$col} SET DEFAULT {$def}");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_codigos');
    }
};
