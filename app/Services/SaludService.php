<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Chequeos rápidos del estado técnico del sistema, para mostrar en el panel del vendedor. */
class SaludService
{
    /** @return list<array{nivel:string,titulo:string,detalle:string}> nivel: ok | warn | bad */
    public static function chequeos(): array
    {
        $r = [];

        try {
            DB::select('select 1');
            $r[] = self::item('ok', 'Base de datos', 'Conectada ('.DB::getDriverName().').');
        } catch (\Throwable $e) {
            return [self::item('bad', 'Base de datos', 'No se puede conectar: '.$e->getMessage())];
        }

        $pend = self::migracionesPendientes();
        $r[] = $pend === 0
            ? self::item('ok', 'Actualizaciones de la base', 'Todo al día.')
            : self::item('bad', 'Actualizaciones de la base', "Hay {$pend} pendiente(s). Entrá a Herramientas y usá «Preparar base de datos».");

        $r[] = config('app.debug')
            ? self::item(app()->isProduction() ? 'bad' : 'warn', 'Modo de depuración', 'APP_DEBUG está activo: en producción tiene que estar en false para no mostrar errores técnicos.')
            : self::item('ok', 'Modo de depuración', 'Apagado.');

        $r[] = is_writable(storage_path()) && is_writable(storage_path('logs'))
            ? self::item('ok', 'Carpeta storage', 'Se puede escribir.')
            : self::item('bad', 'Carpeta storage', 'No se puede escribir: el sistema no podrá guardar sesiones ni registros.');

        $libre = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($libre !== false && $total) {
            $pct = $libre * 100 / $total;
            $r[] = $pct >= 10
                ? self::item('ok', 'Espacio en disco', number_format($pct, 0).'% libre.')
                : self::item($pct >= 4 ? 'warn' : 'bad', 'Espacio en disco', 'Queda solo '.number_format($pct, 1).'% libre.');
        }

        $r[] = config('mail.default') && ! in_array(config('mail.default'), ['log', 'array'], true)
            ? self::item('ok', 'Correo saliente', 'Configurado ('.config('mail.default').').')
            : self::item('warn', 'Correo saliente', 'No está configurado: no se envían correos (demos, avisos).');

        $latido = (int) Cache::get('scheduler_latido', 0);
        $r[] = $latido && now()->timestamp - $latido < 600
            ? self::item('ok', 'Tareas programadas (cron)', 'Funcionando.')
            : self::item('warn', 'Tareas programadas (cron)', 'No hay señal reciente. Sin cron no se hacen los respaldos ni la contabilidad automática. Configurá "php artisan schedule:run" cada minuto.');

        $ultimo = null;
        foreach (RespaldosVendedor::listar() as $b) {
            $ultimo = $ultimo === null ? $b['fecha'] : max($ultimo, $b['fecha']);
        }
        if ($ultimo === null) {
            $r[] = self::item('warn', 'Respaldos', 'Todavía no hay ningún respaldo. Creá uno desde la sección Respaldos.');
        } else {
            $horas = (int) ((now()->timestamp - $ultimo) / 3600);
            $r[] = $horas <= 48
                ? self::item('ok', 'Respaldos', 'El último tiene '.($horas < 1 ? 'menos de una hora' : $horas.' h').'.')
                : self::item('warn', 'Respaldos', 'El último respaldo tiene '.intdiv($horas, 24).' días.');
        }

        return $r;
    }

    /** @param list<array{nivel:string}> $items */
    public static function peor(array $items): string
    {
        $niveles = array_column($items, 'nivel');

        return in_array('bad', $niveles, true) ? 'bad' : (in_array('warn', $niveles, true) ? 'warn' : 'ok');
    }

    public static function migracionesPendientes(): int
    {
        try {
            $m = app('migrator');
            if (! $m->repositoryExists()) {
                return 0;
            }
            $archivos = array_keys($m->getMigrationFiles(array_merge($m->paths(), [database_path('migrations')])));

            return count(array_diff($archivos, $m->getRepository()->getRan()));
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function item(string $nivel, string $titulo, string $detalle): array
    {
        return compact('nivel', 'titulo', 'detalle');
    }
}
