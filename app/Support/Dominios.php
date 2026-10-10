<?php

namespace App\Support;

/**
 * Página pública y sistema en dominios distintos (opcional).
 *
 *   LANDING_DOMINIO=dbstock.com.py          -> la página pública
 *   LANDING_DOMINIO_APP=app.dbstock.com.py  -> el sistema (login, panel, vendedor)
 *
 * Si falta alguno de los dos, todo funciona en un único dominio como hasta ahora.
 */
class Dominios
{
    public static function web(): string
    {
        return self::limpiar((string) config('landing.dominio_web'));
    }

    public static function app(): string
    {
        return self::limpiar((string) config('landing.dominio_app'));
    }

    public static function separados(): bool
    {
        return self::web() !== '' && self::app() !== '' && self::web() !== self::app();
    }

    public static function esWeb(string $host): bool
    {
        $host = strtolower($host);

        return self::separados() && in_array($host, [self::web(), 'www.'.self::web()], true);
    }

    public static function esApp(string $host): bool
    {
        return self::separados() && strtolower($host) === self::app();
    }

    /** Dirección completa dentro del sistema, p. ej. urlApp('/login'). Sin dominios separados usa el dominio actual. */
    public static function urlApp(string $ruta = '/'): string
    {
        if (! self::separados()) {
            return url($ruta);
        }
        $esquema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $esquema.'://'.self::app().'/'.ltrim($ruta, '/');
    }

    /** Dirección completa dentro de la página pública, p. ej. urlWeb('/#planes'). Sin dominios separados usa el dominio actual. */
    public static function urlWeb(string $ruta = '/'): string
    {
        if (! self::separados()) {
            return url($ruta);
        }
        $esquema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $esquema.'://'.self::web().'/'.ltrim($ruta, '/');
    }

    private static function limpiar(string $v): string
    {
        $v = strtolower(trim($v));
        $v = preg_replace('#^https?://#', '', $v);

        return rtrim((string) $v, '/');
    }
}
