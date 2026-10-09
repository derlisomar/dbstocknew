<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Services\AuditoriaService;
use Carbon\CarbonTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa las fechas ya guardadas de UTC a la hora de Paraguay (una sola vez).
 *
 * Hasta la Parte 2 el sistema guardaba las fechas en UTC (3 horas adelantadas respecto de Paraguay).
 * Desde la Parte 3 guarda en America/Asuncion. Este comando corrige las filas viejas para que todo
 * el historial muestre la hora real.
 *
 *   php artisan tiempo:convertir               (solo muestra qué haría)
 *   php artisan tiempo:convertir --confirmar   (lo hace, dentro de una transacción)
 *
 * Hacerlo con el sistema en mantenimiento ("php artisan down"): las filas nuevas ya vienen en hora local
 * y no deben convertirse.
 */
class ConvertirZonaHoraria extends Command
{
    protected $signature = 'tiempo:convertir {--desde=UTC : Zona en la que están guardadas las fechas viejas} {--confirmar : Ejecuta la conversión}';

    protected $description = 'Convierte las fechas guardadas en UTC a la zona horaria del sistema (una sola vez)';

    private const OMITIR = ['migrations', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const MARCA = 'CONVERSION_ZONA_HORARIA';

    public function handle(): int
    {
        $origen = (string) $this->option('desde');
        $destino = (string) config('app.timezone');

        try {
            new \DateTimeZone($origen);
        } catch (\Throwable) {
            $this->error("Zona de origen no válida: {$origen}");

            return self::FAILURE;
        }

        if ($origen === $destino) {
            $this->info("El sistema ya usa {$destino}: no hay nada que convertir.");

            return self::SUCCESS;
        }

        if (Schema::hasTable('auditoria') && Auditoria::where('aud_accion', self::MARCA)->exists()) {
            $this->error('La conversión ya se hizo antes (figura en la auditoría). No se repite para no correr las horas dos veces.');

            return self::FAILURE;
        }

        $columnas = $this->columnasDeFecha();
        $ejecutar = (bool) $this->option('confirmar');

        $this->line("Se convertirá de {$origen} a {$destino}:");
        $filas = [];
        foreach ($columnas as [$tabla, $columna]) {
            $filas[] = [$tabla, $columna, DB::table($tabla)->whereNotNull($columna)->count()];
        }
        $this->table(['Tabla', 'Columna', 'Filas con fecha'], $filas);

        if (! $ejecutar) {
            $this->warn('Es una simulación: no se cambió nada. Con una copia de seguridad hecha y el sistema en mantenimiento, ejecutá de nuevo con --confirmar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($columnas, $origen, $destino) {
            foreach ($columnas as [$tabla, $columna]) {
                $this->convertir($tabla, $columna, $origen, $destino);
            }

            AuditoriaService::registrar(self::MARCA, null, null, ['desde' => $origen, 'hasta' => $destino, 'columnas' => count($columnas)]);
        });

        $this->info('Conversión terminada. Ya podés habilitar el sistema ("php artisan up").');

        return self::SUCCESS;
    }

    /** @return list<array{0: string, 1: string}> */
    private function columnasDeFecha(): array
    {
        $resultado = [];

        foreach (Schema::getTables() as $t) {
            $tabla = $t['name'];
            if (in_array($tabla, self::OMITIR, true)) {
                continue;
            }

            foreach (Schema::getColumns($tabla) as $c) {
                $tipo = strtolower($c['type'] ?? '');
                $nombre = strtolower($c['type_name'] ?? '');

                // PostgreSQL: solo "timestamp without time zone". Las columnas "con zona" ya son un instante exacto.
                $esFecha = DB::getDriverName() === 'pgsql'
                    ? ($nombre === 'timestamp' && str_contains($tipo, 'without time zone'))
                    : in_array($nombre, ['datetime', 'timestamp'], true);

                if ($esFecha) {
                    $resultado[] = [$tabla, $c['name']];
                }
            }
        }

        return $resultado;
    }

    private function convertir(string $tabla, string $columna, string $origen, string $destino): void
    {
        $t = DB::getQueryGrammar()->wrapTable($tabla);
        $c = DB::getQueryGrammar()->wrap($columna);

        if (DB::getDriverName() === 'pgsql') {
            // Respeta el horario de verano que tuvo Paraguay hasta octubre de 2024.
            DB::update("UPDATE {$t} SET {$c} = ({$c} AT TIME ZONE ?) AT TIME ZONE ? WHERE {$c} IS NOT NULL", [$origen, $destino]);

            return;
        }

        // SQLite (solo pruebas): desplazamiento fijo de hoy.
        $segundos = CarbonTimeZone::create($destino)->getOffset(now()) - CarbonTimeZone::create($origen)->getOffset(now());
        DB::update("UPDATE {$t} SET {$c} = datetime({$c}, ?) WHERE {$c} IS NOT NULL", [sprintf('%+d seconds', $segundos)]);
    }
}
