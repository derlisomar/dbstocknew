<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 2: columnas y tablas nuevas + índices.
 * Es seguro correrla más de una vez: cada paso revisa antes si ya existe.
 * No borra ni modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------ columnas nuevas
        $this->agregarColumnas('caja_movimientos', function (Blueprint $t) {
            // EFECTIVO / TRANSFERENCIA / TARJETA_CREDITO / TARJETA_DEBITO / QR (null = movimientos viejos, se tratan como efectivo)
            $this->col($t, 'caja_movimientos', 'mov_forma_pago', fn () => $t->string('mov_forma_pago', 30)->nullable());
            $this->col($t, 'caja_movimientos', 'vta_id', fn () => $t->unsignedBigInteger('vta_id')->nullable());
            $this->col($t, 'caja_movimientos', 'cob_id', fn () => $t->unsignedBigInteger('cob_id')->nullable());
        });

        $this->agregarColumnas('caja_sesiones', function (Blueprint $t) {
            foreach (['gs', 'usd', 'brl'] as $m) {
                $this->col($t, 'caja_sesiones', "ses_esperado_$m", fn () => $t->decimal("ses_esperado_$m", 18, 2)->nullable());
                $this->col($t, 'caja_sesiones', "ses_diferencia_$m", fn () => $t->decimal("ses_diferencia_$m", 18, 2)->nullable());
            }
            $this->col($t, 'caja_sesiones', 'ses_observacion_cierre', fn () => $t->string('ses_observacion_cierre', 255)->nullable());
        });

        $this->agregarColumnas('ventas', function (Blueprint $t) {
            $this->col($t, 'ventas', 'vta_anulada_por', fn () => $t->unsignedBigInteger('vta_anulada_por')->nullable());
            $this->col($t, 'ventas', 'vta_anulada_fecha', fn () => $t->timestamp('vta_anulada_fecha')->nullable());
            $this->col($t, 'ventas', 'vta_motivo_anulacion', fn () => $t->string('vta_motivo_anulacion', 255)->nullable());
        });

        $this->agregarColumnas('cobranzas', function (Blueprint $t) {
            $this->col($t, 'cobranzas', 'cob_formapago', fn () => $t->string('cob_formapago', 30)->nullable());
        });

        // ------------------------------------------------------------ tablas nuevas
        if (! Schema::hasTable('devoluciones')) {
            Schema::create('devoluciones', function (Blueprint $t) {
                $t->increments('dev_id');
                $t->unsignedBigInteger('vta_id');
                $t->unsignedBigInteger('usu_id')->nullable();
                $t->unsignedBigInteger('ses_id')->nullable();
                $t->timestamp('dev_fecha')->useCurrent();
                $t->decimal('dev_total', 18, 2);
                $t->string('dev_motivo', 255)->nullable();
                $t->index('vta_id');
            });
        }

        if (! Schema::hasTable('detalle_devoluciones')) {
            Schema::create('detalle_devoluciones', function (Blueprint $t) {
                $t->increments('ddv_id');
                $t->unsignedBigInteger('dev_id');
                $t->unsignedBigInteger('det_vta_id');
                $t->unsignedBigInteger('pro_id');
                $t->decimal('ddv_cantidad', 18, 2);
                $t->decimal('ddv_preciounitario', 18, 2);
                $t->decimal('ddv_subtotal', 18, 2);
                $t->index('dev_id');
            });
        }

        if (! Schema::hasTable('auditoria')) {
            Schema::create('auditoria', function (Blueprint $t) {
                $t->increments('aud_id');
                $t->unsignedBigInteger('usu_id')->nullable();
                $t->string('aud_accion', 60);
                $t->string('aud_tabla', 60)->nullable();
                $t->string('aud_registro_id', 40)->nullable();
                $t->text('aud_detalle')->nullable();
                $t->string('aud_ip', 45)->nullable();
                $t->timestamp('aud_fecha')->useCurrent();
                $t->index(['aud_tabla', 'aud_registro_id']);
                $t->index('aud_fecha');
            });
        }

        // ------------------------------------------------------------ índices únicos (reglas de integridad)
        $this->verificarSinDuplicados();

        // Una sola sesión ABIERTA por caja, aunque dos personas aprieten "abrir" a la vez.
        $this->indice('uq_caja_sesion_abierta', 'caja_sesiones', ['caj_id'], "ses_estado = 'ABIERTA'", true);

        // Una factura no puede repetirse dentro de la misma sucursal y timbrado.
        $this->indice('uq_ventas_factura', 'ventas', ['suc_id', 'vta_timbrado', 'vta_nro_factura'], 'vta_nro_factura IS NOT NULL', true);

        // ------------------------------------------------------------ índices de búsqueda
        $this->indice('ix_ventas_fecha', 'ventas', ['vta_fecha']);
        $this->indice('ix_ventas_cliente', 'ventas', ['cli_id']);
        $this->indice('ix_ventas_sesion', 'ventas', ['ses_id']);
        $this->indice('ix_detalle_ventas_venta', 'detalle_ventas', ['vta_id']);
        $this->indice('ix_detalle_ventas_producto', 'detalle_ventas', ['pro_id']);
        $this->indice('ix_caja_mov_sesion', 'caja_movimientos', ['ses_id']);
        $this->indice('ix_caja_mov_venta', 'caja_movimientos', ['vta_id']);
        $this->indice('ix_cuentas_cobrar_cliente', 'cuentas_cobrar', ['cli_id', 'cred_estado']);
        $this->indice('ix_cuentas_cobrar_venta', 'cuentas_cobrar', ['vta_id']);
        $this->indice('ix_cobranzas_sesion', 'cobranzas', ['ses_id']);
        $this->indice('ix_detalle_cobranzas_cuenta', 'detalle_cobranzas', ['cred_id']);
        $this->indice('ix_sesiones_usuario', 'caja_sesiones', ['usu_id', 'ses_estado']);
    }

    public function down(): void
    {
        foreach (['uq_caja_sesion_abierta', 'uq_ventas_factura', 'ix_ventas_fecha', 'ix_ventas_cliente', 'ix_ventas_sesion',
                  'ix_detalle_ventas_venta', 'ix_detalle_ventas_producto', 'ix_caja_mov_sesion', 'ix_caja_mov_venta',
                  'ix_cuentas_cobrar_cliente', 'ix_cuentas_cobrar_venta', 'ix_cobranzas_sesion',
                  'ix_detalle_cobranzas_cuenta', 'ix_sesiones_usuario'] as $nombre) {
            DB::statement("DROP INDEX IF EXISTS {$nombre}");
        }

        // Las tablas nuevas (devoluciones, auditoría) y las columnas agregadas NO se borran
        // al volver atrás, para no perder historial. Si de verdad las querés quitar, hacelo a mano.
    }

    // ---------------------------------------------------------------- ayudas

    private function agregarColumnas(string $tabla, \Closure $definir): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }

        Schema::table($tabla, $definir);
    }

    /** Agrega la columna solo si todavía no existe. */
    private function col(Blueprint $t, string $tabla, string $columna, \Closure $crear): void
    {
        if (! Schema::hasColumn($tabla, $columna)) {
            $crear();
        }
    }

    private function indice(string $nombre, string $tabla, array $columnas, ?string $donde = null, bool $unico = false): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }
        foreach ($columnas as $c) {
            if (! Schema::hasColumn($tabla, $c)) {
                return;
            }
        }

        $sql = 'CREATE '.($unico ? 'UNIQUE ' : '')."INDEX IF NOT EXISTS {$nombre} ON {$tabla} (".implode(', ', $columnas).')';
        if ($donde) {
            $sql .= " WHERE {$donde}";
        }

        DB::statement($sql);
    }

    /** Si ya hay datos repetidos, el índice único no se puede crear: se avisa con claridad. */
    private function verificarSinDuplicados(): void
    {
        if (Schema::hasTable('caja_sesiones') && Schema::hasColumn('caja_sesiones', 'ses_estado')) {
            $dup = DB::table('caja_sesiones')->where('ses_estado', 'ABIERTA')
                ->select('caj_id')->groupBy('caj_id')->havingRaw('COUNT(*) > 1')->pluck('caj_id');

            if ($dup->isNotEmpty()) {
                throw new \RuntimeException('Hay cajas con más de una sesión ABIERTA (caj_id: '.$dup->implode(', ').'). Cerrá las sobrantes y volvé a correr la migración.');
            }
        }

        if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'vta_nro_factura')) {
            $dup = DB::table('ventas')->whereNotNull('vta_nro_factura')
                ->select('suc_id', 'vta_timbrado', 'vta_nro_factura')
                ->groupBy('suc_id', 'vta_timbrado', 'vta_nro_factura')->havingRaw('COUNT(*) > 1')->get();

            if ($dup->isNotEmpty()) {
                $lista = $dup->map(fn ($d) => "suc {$d->suc_id} / timbrado {$d->vta_timbrado} / nro {$d->vta_nro_factura}")->implode('; ');
                throw new \RuntimeException('Hay números de factura repetidos: '.$lista.'. Corregilos y volvé a correr la migración.');
            }
        }
    }
};
