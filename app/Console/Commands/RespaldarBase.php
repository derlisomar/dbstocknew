<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RespaldarBase extends Command
{
    protected $signature = 'respaldo:base';

    protected $description = 'Genera un respaldo de la base de datos (PostgreSQL) y borra los más viejos';

    public function handle(RespaldoService $respaldos): int
    {
        try {
            $r = $respaldos->crear();
        } catch (\Throwable $e) {
            Log::error('Falló el respaldo de la base de datos', ['mensaje' => $e->getMessage()]);
            $this->error('No se pudo generar el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Respaldo listo: %s (%s MB)', $r['archivo'], number_format($r['bytes'] / 1048576, 2)));

        return self::SUCCESS;
    }
}
