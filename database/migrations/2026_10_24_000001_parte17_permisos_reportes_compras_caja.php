<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Parte 17: permisos de los reportes de compras/proveedores y de caja/finanzas. */
return new class extends Migration
{
    private const NUEVOS = [
        'REPORTES_COMPRAS' => 'Ver reportes de compras y proveedores (compras, cuentas por pagar, pagos, evolución de costos)',
        'REPORTES_CAJA' => 'Ver reportes de caja y finanzas (cierres, faltantes y sobrantes, flujo de caja)',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permisos') || ! Schema::hasTable('roles') || ! Schema::hasTable('rol_permisos')) {
            return;
        }

        $ids = [];
        foreach (self::NUEVOS as $codigo => $desc) {
            $id = DB::table('permisos')->where('perm_codigo', $codigo)->value('perm_id')
                ?: DB::table('permisos')->insertGetId(['perm_codigo' => $codigo, 'perm_descripcion' => $desc, 'perm_modulo' => 'REPORTES'], 'perm_id');
            $ids[$codigo] = $id;
        }

        $dar = function (int $rol, array $codigos) use ($ids) {
            foreach ($codigos as $c) {
                if (! DB::table('rol_permisos')->where('rol_id', $rol)->where('perm_id', $ids[$c])->exists()) {
                    DB::table('rol_permisos')->insert(['rol_id' => $rol, 'perm_id' => $ids[$c]]);
                }
            }
        };

        $verId = DB::table('permisos')->where('perm_codigo', 'REPORTES_VER')->value('perm_id');
        foreach ($verId ? DB::table('rol_permisos')->where('perm_id', $verId)->pluck('rol_id')->all() : [] as $rol) {
            $dar($rol, array_keys($ids));
        }
        foreach (DB::table('roles')->get() as $rol) {
            $n = mb_strtolower($rol->rol_nombre);
            if ($n === 'gerente') {
                $dar($rol->rol_id, array_keys($ids));
            } elseif ($n === 'encargado de stock') {
                $dar($rol->rol_id, ['REPORTES_COMPRAS']);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permisos')) {
            return;
        }
        $pid = DB::table('permisos')->whereIn('perm_codigo', array_keys(self::NUEVOS))->pluck('perm_id')->all();
        DB::table('rol_permisos')->whereIn('perm_id', $pid)->delete();
        DB::table('permisos')->whereIn('perm_id', $pid)->delete();
    }
};
