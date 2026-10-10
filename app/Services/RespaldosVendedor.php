<?php

namespace App\Services;

/** Lista y localiza los respaldos que hizo RespaldoService (solo archivos con el nombre propio del sistema). */
class RespaldosVendedor
{
    /** @return list<array{nombre:string,bytes:int,fecha:int}> los más nuevos primero */
    public static function listar(): array
    {
        $dir = rtrim((string) config('respaldos.directorio'), '/\\');
        $out = [];
        foreach (@scandir($dir) ?: [] as $nombre) {
            if (preg_match(RespaldoService::PATRON, $nombre) && is_file($dir.'/'.$nombre)) {
                $out[] = ['nombre' => $nombre, 'bytes' => (int) filesize($dir.'/'.$nombre), 'fecha' => (int) filemtime($dir.'/'.$nombre)];
            }
        }
        usort($out, fn ($a, $b) => $b['fecha'] <=> $a['fecha']);

        return $out;
    }

    /** Ruta completa si el nombre es válido y existe; null en cualquier otro caso (nunca se arma una ruta con texto libre). */
    public static function ruta(string $nombre): ?string
    {
        if (! preg_match(RespaldoService::PATRON, $nombre)) {
            return null;
        }
        $ruta = rtrim((string) config('respaldos.directorio'), '/\\').'/'.$nombre;

        return is_file($ruta) ? $ruta : null;
    }
}
