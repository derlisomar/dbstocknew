<?php

namespace App\Console\Commands;

use App\Services\CorreoPrueba;
use Illuminate\Console\Command;

class CorreoProbar extends Command
{
    protected $signature = 'correo:probar {destino : Correo al que se manda la prueba}';

    protected $description = 'Manda un correo de prueba con la configuración actual y explica si falla';

    public function handle(): int
    {
        $r = CorreoPrueba::enviar((string) $this->argument('destino'));

        foreach ($r['config'] as $k => $v) {
            $this->line(sprintf('  %-18s %s', $k, $v));
        }
        $this->newLine();
        $r['ok'] ? $this->info($r['mensaje']) : $this->error($r['mensaje']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
