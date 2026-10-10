<?php

namespace App\Console\Commands;

use App\Services\ContabilidadService;
use Illuminate\Console\Command;

class ContabilidadPreparar extends Command
{
    protected $signature = 'contabilidad:preparar {--desde= : Fecha desde la que se contabiliza (AAAA-MM-DD). Por defecto, hoy}';

    protected $description = 'Crea el plan de cuentas base y deja lista la contabilidad (se puede correr más de una vez)';

    public function handle(ContabilidadService $c): int
    {
        $desde = $this->option('desde');
        if ($desde && ! strtotime($desde)) {
            $this->error('La fecha no es válida. Usá el formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        $nuevas = $c->preparar($desde ?: null);
        $this->info("Plan de cuentas listo ({$nuevas} cuentas nuevas). Se contabiliza desde el ".$c->inicio()->format('d/m/Y').'.');

        return self::SUCCESS;
    }
}
