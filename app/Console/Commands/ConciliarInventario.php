<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class ConciliarInventario extends Command
{
    protected $signature = 'inventario:conciliar';

    protected $description = 'Lista los productos cuyo stock no coincide con la suma de sus movimientos (no modifica nada)';

    public function handle(StockService $stock): int
    {
        $descuadres = $stock->descuadres();

        if ($descuadres->isEmpty()) {
            $this->info('Todo el stock coincide con su historial.');

            return self::SUCCESS;
        }

        $this->table(
            ['Producto', 'Stock actual', 'Suma del historial', 'Diferencia'],
            $descuadres->map(fn ($d) => [
                "#{$d->pro_id} {$d->pro_nombre}",
                (float) $d->pro_stockactual,
                (float) $d->suma_movimientos,
                round((float) $d->pro_stockactual - (float) $d->suma_movimientos, 2),
            ])->all()
        );
        $this->warn($descuadres->count().' producto(s) con diferencia. Corregilos en Inventario con un "Conteo físico".');

        return self::FAILURE;
    }
}
