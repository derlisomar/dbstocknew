<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 16: permisos del Centro de Reportes (por área + costos + exportar).
 * Quien ya tenía REPORTES_VER conserva todo lo que veía antes: recibe todos los permisos nuevos.
 */
return new class extends Migration
{
    private const NUEVOS = [
        'REPORTES_VENTAS' => 'Ver reportes de ventas (por período, cajero, forma de pago, categoría, horas pico, anulaciones)',
        'REPORTES_STOCK' => 'Ver reportes de stock (valorizado, reposición, vencimientos, kardex, ajustes)',
        'REPORTES_CARTERA' => 'Ver reportes de clientes y cartera (cuentas por cobrar, cobranzas, mejores clientes, límites de crédito)',
        'REPORTES_COSTOS' => 'Ver costos, utilidades y márgenes dentro de los reportes',
        'REPORTES_EXPORTAR' => 'Descargar reportes en Excel y PDF',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permisos') || ! Schema::hasTable('roles') || ! Schema::hasTable('rol_permisos')) {
            return;
        }

        $ids = [];
        foreach (self::NUEVOS as $codigo => $desc) {
            $id = DB::table('permisos')->where('perm_codigo', $codigo)->value('perm_id');
            if (! $id) {
                $id = DB::table('permisos')->insertGetId(['perm_codigo' => $codigo, 'perm_descripcion' => $desc, 'perm_modulo' => 'REPORTES'], 'perm_id');
            }
            $ids[$codigo] = $id;
        }

        $dar = function (int $rol, array $codigos) use ($ids) {
            foreach ($codigos as $c) {
                $existe = DB::table('rol_permisos')->where('rol_id', $rol)->where('perm_id', $ids[$c])->exists();
                if (! $existe) {
                    DB::table('rol_permisos')->insert(['rol_id' => $rol, 'perm_id' => $ids[$c]]);
                }
            }
        };

        $verId = DB::table('permisos')->where('perm_codigo', 'REPORTES_VER')->value('perm_id');
        $conVer = $verId ? DB::table('rol_permisos')->where('perm_id', $verId)->pluck('rol_id')->all() : [];
        foreach ($conVer as $rol) {
            $dar($rol, array_keys($ids));
        }

        foreach (DB::table('roles')->get() as $rol) {
            $n = mb_strtolower($rol->rol_nombre);
            if ($n === 'gerente') {
                $dar($rol->rol_id, array_keys($ids));
            } elseif ($n === 'encargado de stock') {
                $dar($rol->rol_id, ['REPORTES_STOCK', 'REPORTES_EXPORTAR']);
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
