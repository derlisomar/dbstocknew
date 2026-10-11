<?php

namespace App\Support;

/**
 * Tipos de comprobante que puede imprimir una caja (columna cajas.caj_tipo_impresion).
 * Cada caja usa UNO solo: ticket (térmica de 72 mm) o matricial (hoja continua de 80 columnas),
 * y en cada uno, comprobante simple (sin valor fiscal) o factura (con timbrado y numeración).
 */
class TipoImpresion
{
    public const TICKET_SIMPLE = 'TICKET_SIMPLE';
    public const TICKET_FACTURA = 'TICKET_FACTURA';
    public const MATRICIAL_SIMPLE = 'MATRICIAL_SIMPLE';
    public const MATRICIAL_FACTURA = 'MATRICIAL_FACTURA';

    /** @return array<string,string> código => nombre para mostrar */
    public static function nombres(): array
    {
        return [
            self::TICKET_SIMPLE => 'Ticket común (sin valor fiscal)',
            self::TICKET_FACTURA => 'Ticket factura (con datos fiscales)',
            self::MATRICIAL_SIMPLE => 'Matricial común (sin valor fiscal)',
            self::MATRICIAL_FACTURA => 'Matricial factura (con datos fiscales)',
        ];
    }

    /** @return list<string> */
    public static function codigos(): array
    {
        return array_keys(self::nombres());
    }

    public static function nombre(?string $tipo): string
    {
        return self::nombres()[$tipo] ?? self::nombres()[self::TICKET_SIMPLE];
    }

    public static function esFactura(?string $tipo): bool
    {
        return in_array($tipo, [self::TICKET_FACTURA, self::MATRICIAL_FACTURA], true);
    }

    public static function esMatricial(?string $tipo): bool
    {
        return in_array($tipo, [self::MATRICIAL_SIMPLE, self::MATRICIAL_FACTURA], true);
    }

    /** Dirección de la hoja a imprimir para una venta. */
    public static function ruta(?string $tipo, int|string $ventaId): string
    {
        return match ($tipo) {
            self::TICKET_FACTURA => '/pdv/ticket-factura/'.$ventaId,
            self::MATRICIAL_SIMPLE => '/pdv/matricial-simple/'.$ventaId,
            self::MATRICIAL_FACTURA => '/pdv/matricial-factura/'.$ventaId,
            default => '/pdv/ticket-simple/'.$ventaId,
        };
    }
}
