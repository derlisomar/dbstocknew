<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\User;

/**
 * Único lugar donde se mueve dinero de la caja.
 *
 * Reglas:
 *  - Todo movimiento queda en el libro (caja_movimientos) con su forma de pago.
 *  - SOLO el EFECTIVO modifica el saldo físico de la caja (cajas.caj_saldo_*).
 *    Tarjeta, QR y transferencia quedan en el libro pero no cambian lo que hay en el cajón.
 *  - Un egreso en efectivo no puede dejar el saldo en negativo (salvo devoluciones/anulaciones,
 *    que se registran siempre y se marcan en el arqueo).
 */
class CajaService
{
    public const MONEDAS = ['GS', 'USD', 'BRL'];

    /** Sesión ABIERTA de un usuario (bloqueada para la transacción en curso). */
    public function sesionAbiertaDe(User $usuario, ?int $cajId = null, bool $bloquear = true): ?CajaSesion
    {
        $consulta = CajaSesion::with('caja')
            ->where('usu_id', $usuario->usu_id)
            ->where('ses_estado', 'ABIERTA');

        if ($cajId) {
            $consulta->where('caj_id', $cajId);
        }

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first();
    }

    public function registrar(
        CajaSesion $sesion,
        string $tipo,
        float $monto,
        string $moneda,
        string $concepto,
        string $formaPago = 'EFECTIVO',
        array $extra = [],
        bool $permitirSaldoNegativo = false
    ): CajaMovimiento {
        $moneda = strtoupper($moneda);

        if (! in_array($tipo, ['INGRESO', 'EGRESO'], true)) {
            throw new \InvalidArgumentException('Tipo de movimiento inválido.');
        }
        if (! in_array($moneda, self::MONEDAS, true)) {
            throw new NegocioException('Moneda no válida.');
        }
        if ($monto <= 0) {
            throw new NegocioException('El monto debe ser mayor a cero.');
        }
        if ($sesion->ses_estado !== 'ABIERTA') {
            throw new NegocioException('La sesión de caja está cerrada.');
        }

        // El saldo se toca primero: si falta efectivo, no se escribe nada en el libro.
        if ($formaPago === 'EFECTIVO') {
            $this->moverSaldoFisico($sesion->caj_id, $tipo, $monto, $moneda, $permitirSaldoNegativo);
        }

        return CajaMovimiento::create([
            'ses_id' => $sesion->ses_id,
            'mov_tipo' => $tipo,
            'mov_monto' => round($monto, 2),
            'mov_concepto' => mb_substr($concepto, 0, 255),
            'mov_moneda' => $moneda,
            'mov_forma_pago' => $formaPago,
        ] + $extra);
    }

    private function moverSaldoFisico(int $cajId, string $tipo, float $monto, string $moneda, bool $permitirNegativo): void
    {
        $columna = 'caj_saldo_'.strtolower($moneda);
        $caja = Caja::where('caj_id', $cajId)->lockForUpdate()->firstOrFail();

        if ($tipo === 'EGRESO') {
            if (! $permitirNegativo && (float) $caja->$columna < $monto) {
                throw new NegocioException("La caja no tiene efectivo suficiente en {$moneda} para este egreso.");
            }
            $caja->decrement($columna, $monto);
        } else {
            $caja->increment($columna, $monto);
        }
    }

    /**
     * Efectivo que DEBERÍA haber en el cajón al cerrar la sesión, por moneda:
     * monto inicial + ingresos en efectivo - egresos en efectivo.
     *
     * @return array{GS: float, USD: float, BRL: float}
     */
    public function esperadoDeSesion(CajaSesion $sesion): array
    {
        $esperado = [
            'GS' => (float) $sesion->ses_monto_inicial_gs,
            'USD' => (float) $sesion->ses_monto_inicial_usd,
            'BRL' => (float) $sesion->ses_monto_inicial_brl,
        ];

        // Los movimientos anteriores a esta versión no tienen forma de pago: se tratan como efectivo.
        $filas = CajaMovimiento::where('ses_id', $sesion->ses_id)
            ->whereRaw("COALESCE(mov_forma_pago, 'EFECTIVO') = 'EFECTIVO'")
            ->get(['mov_tipo', 'mov_monto', 'mov_moneda']);

        foreach ($filas as $f) {
            $moneda = strtoupper($f->mov_moneda ?: 'GS');
            if (! isset($esperado[$moneda])) {
                continue;
            }
            $esperado[$moneda] += $f->mov_tipo === 'INGRESO' ? (float) $f->mov_monto : -(float) $f->mov_monto;
        }

        return array_map(fn ($v) => round($v, 2), $esperado);
    }

    /**
     * Lo que ya entró (neto) en el libro de caja por esta venta: ingresos menos egresos vinculados.
     * Devuelve null si la venta no tiene movimientos vinculados (ventas anteriores a esta versión).
     *
     * @return array{monto: float, moneda: string, forma: string}|null
     */
    public function netoDeVenta(int $vtaId): ?array
    {
        return $this->netoPorVinculo('vta_id', $vtaId);
    }

    /** Igual que netoDeVenta, pero para los movimientos vinculados a un cobro. */
    public function netoDeCobro(int $cobId): ?array
    {
        return $this->netoPorVinculo('cob_id', $cobId);
    }

    /** @return array{monto: float, moneda: string, forma: string}|null */
    private function netoPorVinculo(string $columna, int $id): ?array
    {
        $filas = CajaMovimiento::where($columna, $id)->get(['mov_tipo', 'mov_monto', 'mov_moneda', 'mov_forma_pago']);

        if ($filas->isEmpty()) {
            return null;
        }

        $primera = $filas->first();
        $neto = 0.0;
        foreach ($filas as $f) {
            $neto += $f->mov_tipo === 'INGRESO' ? (float) $f->mov_monto : -(float) $f->mov_monto;
        }

        return [
            'monto' => round(max($neto, 0), 2),
            'moneda' => strtoupper($primera->mov_moneda ?: 'GS'),
            'forma' => $primera->mov_forma_pago ?: 'EFECTIVO',
        ];
    }

    /** Quita de la caja los saldos acumulados al cerrar (se retira el efectivo). */
    public function ponerSaldosEnCero(int $cajId): void
    {
        Caja::where('caj_id', $cajId)->lockForUpdate()->first()?->update([
            'caj_saldo_gs' => 0,
            'caj_saldo_usd' => 0,
            'caj_saldo_brl' => 0,
        ]);
    }
}
