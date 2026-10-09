<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 3: datos para anular cobros e índices para las pantallas de auditoría y cierres.
 * Es seguro correrla más de una vez. No borra ni modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cobranzas')) {
            Schema::table('cobranzas', function (Blueprint $t) {
                if (! Schema::hasColumn('cobranzas', 'cob_anulada_por')) {
                    $t->unsignedBigInteger('cob_anulada_por')->nullable();
                }
                if (! Schema::hasColumn('cobranzas', 'cob_anulada_fecha')) {
                    $t->timestamp('cob_anulada_fecha')->nullable();
                }
                if (! Schema::hasColumn('cobranzas', 'cob_motivo_anulacion')) {
                    $t->string('cob_motivo_anulacion', 255)->nullable();
                }
            });
        }

        $this->indice('ix_cobranzas_fecha', 'cobranzas', ['cob_fecha']);
        $this->indice('ix_auditoria_accion', 'auditoria', ['aud_accion']);
        $this->indice('ix_auditoria_usuario', 'auditoria', ['usu_id']);
        $this->indice('ix_caja_sesiones_cierre', 'caja_sesiones', ['ses_fecha_cierre']);
    }

    public function down(): void
    {
        foreach (['ix_cobranzas_fecha', 'ix_auditoria_accion', 'ix_auditoria_usuario', 'ix_caja_sesiones_cierre'] as $nombre) {
            DB::statement("DROP INDEX IF EXISTS {$nombre}");
        }
        // Las columnas nuevas de cobranzas no se borran al volver atrás, para no perder historial.
    }

    private function indice(string $nombre, string $tabla, array $columnas): void
    {
        if (! Schema::hasTable($tabla)) {
            return;
        }
        foreach ($columnas as $c) {
            if (! Schema::hasColumn($tabla, $c)) {
                return;
            }
        }

        DB::statement("CREATE INDEX IF NOT EXISTS {$nombre} ON {$tabla} (".implode(', ', $columnas).')');
    }
};
