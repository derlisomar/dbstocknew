<?php

namespace App\Console\Commands;

use App\Services\RolesBase;
use Illuminate\Console\Command;

class RolesPreparar extends Command
{
    protected $signature = 'roles:preparar {--restablecer : Vuelve a dejar los roles base con sus permisos originales (pisa los cambios hechos en Gestión de Roles)}';

    protected $description = 'Crea los roles listos para usar (Gerente, Cajero, Vendedor, Encargado de stock y Contador) con sus permisos';

    public function handle(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('roles') || ! \Illuminate\Support\Facades\Schema::hasTable('permisos')) {
            $this->error('Todavía no están las tablas de roles y permisos.');

            return self::FAILURE;
        }

        $restablecer = (bool) $this->option('restablecer');
        if ($restablecer && ! $this->confirm('Esto pisa los permisos de los roles base que ya existan. ¿Continuar?', false)) {
            $this->line('No se cambió nada.');

            return self::SUCCESS;
        }

        $r = RolesBase::preparar($restablecer);

        foreach ($r['creados'] as $n) {
            $this->info("  + {$n}: creado");
        }
        foreach ($r['restablecidos'] as $n) {
            $this->warn("  ~ {$n}: permisos restablecidos");
        }
        foreach ($r['existentes'] as $n) {
            $this->line("  = {$n}: ya existía, no se tocó");
        }
        $this->newLine();
        $this->info('Listo. Ajustalos cuando quieras en Gestión de Roles.');

        return self::SUCCESS;
    }
}
