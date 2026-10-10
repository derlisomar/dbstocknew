<?php

namespace App\Console\Commands;

use App\Services\ContabilidadMotor;
use Illuminate\Console\Command;

class ContabilidadSincronizar extends Command
{
    protected $signature = 'contabilidad:sincronizar';

    protected $description = 'Genera los asientos contables de las operaciones que todavía no fueron contabilizadas';

    public function handle(ContabilidadMotor $motor): int
    {
        $r = $motor->sincronizar();

        $this->info("Operaciones contabilizadas: {$r['generados']}");
        foreach ($r['errores'] as $e) {
            $this->warn($e);
        }

        return $r['errores'] ? self::FAILURE : self::SUCCESS;
    }
}
