<?php

namespace App\Services;

use App\Models\Permiso;
use App\Models\Rol;

/**
 * Crea los roles listos para usar definidos en config/permisos.php ('roles_base').
 * Un rol que ya existe con ese nombre (sin distinguir mayúsculas) no se toca, salvo que se pida restablecerlo.
 */
class RolesBase
{
    /**
     * @return array{creados:list<string>, existentes:list<string>, restablecidos:list<string>}
     */
    public static function preparar(bool $restablecer = false): array
    {
        $catalogo = (array) config('permisos.catalogo', []);

        // Los permisos del catálogo tienen que estar cargados antes de asignarlos.
        foreach ($catalogo as $codigo => $info) {
            Permiso::firstOrCreate(['perm_codigo' => $codigo], ['perm_descripcion' => $info['descripcion'], 'perm_modulo' => $info['modulo']]);
        }

        $out = ['creados' => [], 'existentes' => [], 'restablecidos' => []];

        foreach ((array) config('permisos.roles_base', []) as $nombre => $def) {
            $codigos = isset($def['permisos'])
                ? array_values(array_intersect((array) $def['permisos'], array_keys($catalogo)))
                : array_values(array_diff(array_keys($catalogo), (array) ($def['todos_menos'] ?? [])));
            $ids = Permiso::whereIn('perm_codigo', $codigos)->pluck('perm_id')->all();

            $rol = Rol::whereRaw('LOWER(rol_nombre) = ?', [mb_strtolower($nombre)])->first();

            if (! $rol) {
                $rol = Rol::create(['rol_nombre' => $nombre, 'rol_descripcion' => (string) ($def['descripcion'] ?? '')]);
                $rol->permisos()->sync($ids);
                $out['creados'][] = $nombre;
            } elseif ($restablecer && ! self::esAdministrador($rol)) {
                $rol->permisos()->sync($ids);
                $out['restablecidos'][] = $nombre;
            } else {
                $out['existentes'][] = $nombre;
            }
        }

        return $out;
    }

    private static function esAdministrador(Rol $rol): bool
    {
        $admins = array_map('mb_strtolower', (array) config('permisos.roles_admin', []));

        return in_array(mb_strtolower((string) $rol->rol_nombre), $admins, true);
    }
}
