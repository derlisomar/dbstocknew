<?php

namespace App\Console\Commands;

use App\Services\PadronImportador;
use Illuminate\Console\Command;

class PadronImportar extends Command
{
    protected $signature = 'padron:importar {archivos* : Uno o más archivos .txt/.csv/.zip del padrón de RUC de la DNIT}
        {--probar : Solo muestra cómo se leen las primeras líneas, sin guardar nada}';

    protected $description = 'Carga el padrón público de RUC de la DNIT en la tabla local de consulta';

    public function handle(): int
    {
        foreach ($this->argument('archivos') as $ruta) {
            if (! is_file($ruta)) {
                $this->error("No existe: $ruta");

                return self::FAILURE;
            }
            if ($this->option('probar')) {
                $this->info("Vista previa de $ruta");
                $n = 0;
                foreach (PadronImportador::lineas($ruta) as $l) {
                    $r = PadronImportador::leerLinea($l);
                    $this->line($r ? sprintf('  OK  %s-%s  %s  [%s]', $r['pad_ruc'], $r['pad_dv'] ?? '?', $r['pad_nombre'], $r['pad_estado'] ?? '') : '  --  (se ignora) '.mb_substr(trim($l), 0, 80));
                    if (++$n >= 8) {
                        break;
                    }
                }

                continue;
            }
            $this->info("Importando $ruta ...");
            [$leidas, $ign] = PadronImportador::importar($ruta, fn ($n) => $n % 50000 === 0 ? $this->line("  $n registros") : null);
            $this->info("  Listo: $leidas registros cargados, $ign líneas ignoradas.");
        }

        return self::SUCCESS;
    }
}
