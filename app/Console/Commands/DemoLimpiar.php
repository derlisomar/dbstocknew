<?php

namespace App\Console\Commands;

use App\Services\DemoService;
use Illuminate\Console\Command;

class DemoLimpiar extends Command
{
    protected $signature = 'demo:limpiar';

    protected $description = 'Desactiva los usuarios de demo que ya vencieron';

    public function handle(DemoService $demos): int
    {
        $n = $demos->vencerAntiguas();
        $this->info($n === 0 ? 'No había demos vencidas.' : "Se cerraron {$n} demo(s) vencida(s).");

        return self::SUCCESS;
    }
}
