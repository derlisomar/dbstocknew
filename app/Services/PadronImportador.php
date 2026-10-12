<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lee los archivos del padrón de RUC de la DNIT (texto con separador | ; , o tabulación; .txt, .csv o .zip)
 * y los carga en la tabla padron_ruc. Orden esperado de columnas: RUC, razón social, DV, RUC anterior, estado.
 */
class PadronImportador
{
    /** Interpreta una línea. Devuelve null si no es un registro (encabezado, línea vacía). */
    public static function leerLinea(string $linea): ?array
    {
        $linea = trim($linea, "\xEF\xBB\xBF \t\r\n");
        if ($linea === '') {
            return null;
        }
        if (! mb_check_encoding($linea, 'UTF-8')) {
            $linea = mb_convert_encoding($linea, 'UTF-8', 'ISO-8859-1');
        }
        $sep = '|';
        foreach (['|', ';', "\t", ','] as $s) {
            if (substr_count($linea, $s) >= 2) {
                $sep = $s;
                break;
            }
        }
        $p = array_map(fn ($x) => trim($x, " \t\"'"), explode($sep, $linea));
        $ruc = $p[0] ?? '';
        if (! preg_match('/^\d{1,12}[A-Za-z]?$/', $ruc) || trim($p[1] ?? '') === '') {
            return null;
        }
        $dv = $p[2] ?? '';

        return [
            'pad_ruc' => $ruc,
            'pad_dv' => preg_match('/^\d$/', $dv) ? $dv : null,
            'pad_nombre' => mb_substr(preg_replace('/\s+/', ' ', $p[1]), 0, 255),
            'pad_estado' => ($p[4] ?? '') !== '' ? mb_substr($p[4], 0, 40) : null,
        ];
    }

    /** Líneas de un archivo (si es .zip, de todos sus archivos de texto). @return \Generator<string> */
    public static function lineas(string $ruta): \Generator
    {
        if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($ruta) !== true) {
                throw new \RuntimeException("No se pudo abrir el ZIP: $ruta");
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $h = $zip->getStream($zip->getNameIndex($i));
                if (! $h) {
                    continue;
                }
                while (($l = fgets($h)) !== false) {
                    yield $l;
                }
                fclose($h);
            }
            $zip->close();

            return;
        }
        $h = fopen($ruta, 'rb');
        if (! $h) {
            throw new \RuntimeException("No se pudo abrir: $ruta");
        }
        while (($l = fgets($h)) !== false) {
            yield $l;
        }
        fclose($h);
    }

    /** Carga el archivo en la base. Devuelve [leidas, ignoradas]. */
    public static function importar(string $ruta, ?callable $progreso = null): array
    {
        $lote = [];
        $leidas = $ignoradas = 0;
        $volcar = function () use (&$lote) {
            if ($lote) {
                DB::table('padron_ruc')->upsert(array_values($lote), ['pad_ruc'], ['pad_dv', 'pad_nombre', 'pad_estado']);
                $lote = [];
            }
        };
        foreach (self::lineas($ruta) as $linea) {
            $r = self::leerLinea($linea);
            if (! $r) {
                $ignoradas++;

                continue;
            }
            $lote[$r['pad_ruc']] = $r;
            $leidas++;
            if (count($lote) >= 1000) {
                $volcar();
                if ($progreso) {
                    $progreso($leidas);
                }
            }
        }
        $volcar();

        return [$leidas, $ignoradas];
    }
}
