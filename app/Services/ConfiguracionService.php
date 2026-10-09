<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración general del negocio (nombre, logo, edición, módulos, límites, licencia).
 * Se lee una sola vez por pedido. Si la tabla todavía no existe (falta correr la migración de la Parte 8)
 * o no hay datos, el sistema se comporta como siempre: edición completa y sin límites.
 */
class ConfiguracionService
{
    private const CLAVE = 'dbstock.configuracion';

    /** @return array<string,?string> */
    public static function todo(): array
    {
        $app = app();
        if ($app->bound(self::CLAVE)) {
            return $app->make(self::CLAVE);
        }

        $datos = [];
        try {
            if (Schema::hasTable('configuracion_sistema')) {
                $datos = DB::table('configuracion_sistema')->pluck('cfg_valor', 'cfg_clave')->all();
            }
        } catch (\Throwable) {
            $datos = [];
        }

        $app->instance(self::CLAVE, $datos);

        return $datos;
    }

    public static function get(string $clave, $defecto = null)
    {
        $v = self::todo()[$clave] ?? null;

        return ($v === null || $v === '') ? $defecto : $v;
    }

    /** Guarda varios valores. Un valor null o vacío borra la clave. */
    public static function set(array $pares): void
    {
        foreach ($pares as $clave => $valor) {
            if ($valor === null || $valor === '') {
                DB::table('configuracion_sistema')->where('cfg_clave', $clave)->delete();

                continue;
            }

            $valor = is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
            $existe = DB::table('configuracion_sistema')->where('cfg_clave', $clave)->exists();
            $existe
                ? DB::table('configuracion_sistema')->where('cfg_clave', $clave)->update(['cfg_valor' => $valor])
                : DB::table('configuracion_sistema')->insert(['cfg_clave' => $clave, 'cfg_valor' => $valor]);
        }

        app()->forgetInstance(self::CLAVE);
    }

    public static function olvidar(): void
    {
        app()->forgetInstance(self::CLAVE);
    }

    // ------------------------------------------------------------------ negocio

    public static function nombreNegocio(): string
    {
        return (string) self::get('negocio_nombre', 'dbstock');
    }

    /** Dirección web del logo cargado, o null para usar el logo por defecto. */
    public static function logoUrl(): ?string
    {
        $ruta = self::get('negocio_logo');

        return $ruta ? asset($ruta).'?v='.(self::get('negocio_logo_version', '1')) : null;
    }

    // ------------------------------------------------------------------ edición y módulos

    public static function edicion(): string
    {
        $e = strtoupper((string) self::get('edicion', 'COMPLETA'));

        return array_key_exists($e, config('modulos.ediciones', [])) ? $e : 'COMPLETA';
    }

    /** Módulos que activa por defecto una edición. @return list<string> */
    public static function modulosDeEdicion(string $edicion): array
    {
        $activos = [];
        foreach (config('modulos.catalogo', []) as $clave => $m) {
            if ($edicion === 'COMPLETA' || ! empty($m['basica'])) {
                $activos[] = $clave;
            }
        }

        return $activos;
    }

    public static function modulo(string $clave): bool
    {
        $catalogo = config('modulos.catalogo', []);
        if (! isset($catalogo[$clave])) {
            return true; // no es un módulo apagable (núcleo)
        }

        $explicito = self::todo()['mod_'.$clave] ?? null;
        if ($explicito !== null && $explicito !== '') {
            return $explicito === '1';
        }

        return in_array($clave, self::modulosDeEdicion(self::edicion()), true);
    }

    /** Fija exactamente qué módulos quedan activos. @param list<string> $activos */
    public static function fijarModulos(array $activos): void
    {
        $pares = [];
        foreach (array_keys(config('modulos.catalogo', [])) as $clave) {
            $pares['mod_'.$clave] = in_array($clave, $activos, true) ? '1' : '0';
        }
        self::set($pares);
    }

    // ------------------------------------------------------------------ límites

    /** Límite de la cantidad de sucursales, cajas o usuarios activos (0 = sin límite). */
    public static function limite(string $que): int
    {
        return max(0, (int) self::get('limite_'.$que, 0));
    }

    /**
     * Devuelve un mensaje si crear uno más superaría el límite del plan; null si se puede.
     * Debe llamarse ANTES de abrir una transacción.
     */
    public static function limiteExcedido(string $que): ?string
    {
        $limite = self::limite($que);
        if ($limite === 0) {
            return null;
        }

        [$tabla, $columna, $nombre] = [
            'sucursales' => ['sucursales', 'suc_activa', 'sucursales'],
            'cajas' => ['cajas', 'caj_activa', 'cajas'],
            'usuarios' => ['usuarios', 'usu_activo', 'usuarios activos'],
        ][$que];

        $actual = DB::table($tabla)->where($columna, true)->count();
        if ($actual >= $limite) {
            return "Tu plan permite hasta {$limite} {$nombre} y ya tenés {$actual}. Para agregar más, consultá por un plan mayor con tu proveedor.";
        }

        return null;
    }
}
