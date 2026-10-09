<?php

namespace App\Services;

use App\Models\Auditoria;

/**
 * Deja constancia de QUIÉN hizo QUÉ y CUÁNDO en las operaciones sensibles
 * (cierres de caja, anulaciones, devoluciones, cambios de usuarios...).
 * Si no se puede escribir el registro, la operación falla: una acción sin rastro no debe pasar.
 */
class AuditoriaService
{
    public static function registrar(string $accion, ?string $tabla = null, $registroId = null, array|string|null $detalle = null): void
    {
        Auditoria::create([
            'usu_id' => auth()->id(),
            'aud_accion' => $accion,
            'aud_tabla' => $tabla,
            'aud_registro_id' => $registroId === null ? null : (string) $registroId,
            'aud_detalle' => is_array($detalle) ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : $detalle,
            'aud_ip' => request()?->ip(),
            'aud_fecha' => now(),
        ]);
    }
}
