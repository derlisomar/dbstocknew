<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/** Manda un correo de prueba con la configuración actual del .env y explica qué pasó. */
class CorreoPrueba
{
    /**
     * @return array{ok:bool, mensaje:string, config:array<string,string>}
     */
    public static function enviar(string $destino): array
    {
        $config = self::resumen();

        try {
            Mail::raw(
                "Este es un correo de prueba enviado desde dbstock.\n\nSi lo estás leyendo, el envío de correos funciona y las demos de la página pública van a llegar bien.",
                fn ($m) => $m->to($destino)->subject('Prueba de correo de dbstock')
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => self::explicar($e), 'config' => $config];
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return [
                'ok' => false,
                'mensaje' => 'El correo NO salió de verdad: MAIL_MAILER está en "'.config('mail.default').'", que solo lo anota en storage/logs/laravel.log. Configurá SMTP en el archivo .env y limpiá la caché.',
                'config' => $config,
            ];
        }

        return ['ok' => true, 'mensaje' => "Correo enviado a {$destino}. Revisá la bandeja de entrada y también la carpeta de spam.", 'config' => $config];
    }

    /** @return array<string,string> */
    public static function resumen(): array
    {
        $mailer = (string) config('mail.default');
        $c = (array) config("mail.mailers.{$mailer}", []);

        return array_filter([
            'MAIL_MAILER' => $mailer,
            'MAIL_HOST' => (string) ($c['host'] ?? ''),
            'MAIL_PORT' => (string) ($c['port'] ?? ''),
            'MAIL_SCHEME' => (string) ($c['scheme'] ?? ($c['encryption'] ?? '')),
            'MAIL_USERNAME' => (string) ($c['username'] ?? ''),
            'MAIL_FROM_ADDRESS' => (string) config('mail.from.address'),
        ], fn ($v) => $v !== '');
    }

    private static function explicar(\Throwable $e): string
    {
        $m = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
        $m = str_ireplace((string) config('mail.mailers.smtp.password'), '***', $m);
        $pista = match (true) {
            (bool) preg_match('/authenticat|535|credential|password/i', $m) => 'Parece un problema de usuario o contraseña del correo (MAIL_USERNAME / MAIL_PASSWORD).',
            (bool) preg_match('/connect|timed out|refused|resolve|getaddrinfo|network/i', $m) => 'No se pudo conectar al servidor de correo: revisá MAIL_HOST y MAIL_PORT (465 con MAIL_SCHEME=smtps, o 587 con MAIL_SCHEME=null) y que tu internet o firewall no bloquee ese puerto.',
            (bool) preg_match('/certificate|ssl|tls|crypto/i', $m) => 'Problema de seguridad de la conexión (SSL/TLS): probá cambiar entre el puerto 465 (smtps) y el 587.',
            default => 'Revisá los datos MAIL_* del archivo .env.',
        };

        return class_basename($e).': '.$m.' | '.$pista;
    }
}
