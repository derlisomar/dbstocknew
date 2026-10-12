<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 19: ajuste de los roles listos para usar en instalaciones que ya los tienen.
 *  - Vendedor: ahora también vende, cobra y abre/cierra su caja (solo si conserva los permisos originales: no pisa personalizaciones).
 *  - Gerente: ya no entra a contabilidad (se le quita CONTABILIDAD_VER).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permisos') || ! Schema::hasTable('roles') || ! Schema::hasTable('rol_permisos')) {
            return;
        }

        $id = fn (string $codigo) => DB::table('permisos')->where('perm_codigo', $codigo)->value('perm_id');

        $vendedor = DB::table('roles')->whereRaw('LOWER(rol_nombre) = ?', ['vendedor'])->first();
        if ($vendedor) {
            $actuales = DB::table('rol_permisos as rp')->join('permisos as p', 'p.perm_id', '=', 'rp.perm_id')->where('rp.rol_id', $vendedor->rol_id)->pluck('p.perm_codigo')->sort()->values()->all();
            if ($actuales === ['CLIENTES_GESTIONAR', 'PRESUPUESTOS_GESTIONAR']) {
                foreach (['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'COBRANZAS_REGISTRAR'] as $c) {
                    $pid = $id($c);
                    if ($pid && ! DB::table('rol_permisos')->where('rol_id', $vendedor->rol_id)->where('perm_id', $pid)->exists()) {
                        DB::table('rol_permisos')->insert(['rol_id' => $vendedor->rol_id, 'perm_id' => $pid]);
                    }
                }
            }
        }

        $gerente = DB::table('roles')->whereRaw('LOWER(rol_nombre) = ?', ['gerente'])->first();
        if ($gerente && ($pid = $id('CONTABILIDAD_VER'))) {
            DB::table('rol_permisos')->where('rol_id', $gerente->rol_id)->where('perm_id', $pid)->delete();
        }
    }

    public function down(): void
    {
        // Ajuste de datos: no se revierte.
    }
};
