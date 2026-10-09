<?php

namespace App\Console\Commands;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Console\Command;

class SincronizarPermisos extends Command
{
    protected $signature = 'permisos:sincronizar {--dar-todos-a-roles : Asigna TODOS los permisos del catálogo a todos los roles existentes (arranque seguro)}';

    protected $description = 'Carga en la base los permisos de config/permisos.php (los que faltan) y, opcionalmente, se los da a todos los roles';

    public function handle(): int
    {
        $ids = [];
        $nuevos = 0;

        foreach (config('permisos.catalogo', []) as $codigo => $info) {
            $permiso = Permiso::firstOrCreate(
                ['perm_codigo' => $codigo],
                ['perm_descripcion' => $info['descripcion'], 'perm_modulo' => $info['modulo']]
            );

            $ids[] = $permiso->perm_id;
            $nuevos += $permiso->wasRecentlyCreated ? 1 : 0;
        }

        $this->info(count($ids).' permisos en el catálogo ('.$nuevos.' nuevos).');

        if ($this->option('dar-todos-a-roles')) {
            // syncWithoutDetaching: agrega los que faltan y NO quita ninguno que ya tenga el rol
            foreach (Rol::all() as $rol) {
                $rol->permisos()->syncWithoutDetaching($ids);
                $this->line("  - {$rol->rol_nombre}: con todos los permisos");
            }

            $this->warn('Ahora entrá a "Gestión de Roles" y destildá lo que cada rol NO debe poder hacer.');
        }

        return self::SUCCESS;
    }
}
