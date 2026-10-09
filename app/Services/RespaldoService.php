<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Respaldo de la base PostgreSQL con pg_dump (formato comprimido, restaurable con pg_restore).
 * La contraseña viaja por variable de entorno del proceso: no aparece en la lista de procesos ni en logs.
 */
class RespaldoService
{
    public const PATRON = '/^dbstock_\d{8}_\d{6}\.dump$/';

    /** @return array{archivo: string, bytes: int} */
    public function crear(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new \RuntimeException('El respaldo automático solo está preparado para PostgreSQL.');
        }

        $dir = rtrim((string) config('respaldos.directorio'), '/\\');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear la carpeta de respaldos: {$dir}");
        }

        $conf = config('database.connections.pgsql');
        $final = $dir.'/dbstock_'.now()->format('Ymd_His').'.dump';
        $parcial = $final.'.partial';

        // Sin contraseña configurada no se define PGPASSWORD, para que pg_dump pueda usar el archivo .pgpass si existe.
        $env = [];
        if (($conf['password'] ?? '') !== '') {
            $env['PGPASSWORD'] = (string) $conf['password'];
        }
        if (! empty($conf['sslmode'])) {
            $env['PGSSLMODE'] = (string) $conf['sslmode'];
        }

        // 1. Volcado y 2. comprobación de que el archivo generado se puede leer
        //    (un respaldo que no se puede restaurar no sirve). Si algo falla, no queda nada a medias.
        try {
            $this->ejecutar([
                config('respaldos.pg_dump'), '--format=custom', '--no-owner',
                '--host', (string) $conf['host'], '--port', (string) $conf['port'],
                '--username', (string) $conf['username'], '--file', $parcial, (string) $conf['database'],
            ], $env, 'pg_dump', 900);

            $this->ejecutar([config('respaldos.pg_restore'), '--list', $parcial], $env, 'pg_restore', 120);
        } catch (\Throwable $e) {
            @unlink($parcial);
            throw $e;
        }

        if (! @rename($parcial, $final)) {
            @unlink($parcial);
            throw new \RuntimeException('No se pudo guardar el respaldo terminado.');
        }
        @chmod($final, 0600);

        $this->limpiarAntiguos();

        return ['archivo' => $final, 'bytes' => (int) filesize($final)];
    }

    /**
     * Borra respaldos más viejos que "dias", pero deja siempre los últimos "minimo".
     * Solo toca archivos con el nombre propio de este sistema.
     *
     * @return list<string> archivos borrados
     */
    public function limpiarAntiguos(?string $dir = null, ?int $dias = null, ?int $minimo = null): array
    {
        $dir = rtrim($dir ?? (string) config('respaldos.directorio'), '/\\');
        $dias = $dias ?? (int) config('respaldos.dias');
        $minimo = $minimo ?? (int) config('respaldos.minimo');

        $archivos = [];
        foreach (@scandir($dir) ?: [] as $nombre) {
            if (preg_match(self::PATRON, $nombre)) {
                $archivos[$nombre] = (int) filemtime($dir.'/'.$nombre);
            }
        }

        arsort($archivos); // los más nuevos primero
        $limite = time() - $dias * 86400;
        $borrados = [];

        foreach (array_slice($archivos, $minimo, null, true) as $nombre => $fecha) {
            if ($fecha < $limite && @unlink($dir.'/'.$nombre)) {
                $borrados[] = $nombre;
            }
        }

        return $borrados;
    }

    /** Fecha (timestamp) del respaldo más reciente, o null si no hay ninguno. */
    public function ultimo(?string $dir = null): ?int
    {
        $dir = rtrim($dir ?? (string) config('respaldos.directorio'), '/\\');
        $max = null;

        foreach (@scandir($dir) ?: [] as $nombre) {
            if (preg_match(self::PATRON, $nombre)) {
                $max = max($max ?? 0, (int) filemtime($dir.'/'.$nombre));
            }
        }

        return $max;
    }

    private function ejecutar(array $comando, array $env, string $nombre, int $timeout): void
    {
        $proceso = new Process($comando, null, $env, null, $timeout);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            // El mensaje de error de la herramienta puede ayudar; la contraseña nunca está en él.
            throw new \RuntimeException("{$nombre} falló: ".trim($proceso->getErrorOutput() ?: $proceso->getOutput()));
        }
    }
}
