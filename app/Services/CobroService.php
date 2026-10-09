<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CajaSesion;
use App\Models\Cobranza;
use App\Models\CuentasCobrar;
use App\Models\DetalleCobranza;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Anulación de cobros (cobranzas a crédito).
 * Deshace un cobro registrado por error: la deuda vuelve al cliente y el dinero sale de la caja.
 * Todo ocurre en una transacción con las filas bloqueadas.
 */
class CobroService
{
    public function __construct(private CajaService $caja)
    {
    }

    public function anular(int $cobId, User $usuario, ?string $motivo = null): Cobranza
    {
        $motivo = $motivo !== null ? trim($motivo) : '';
        if ($motivo === '') {
            throw new NegocioException('Indicá el motivo de la anulación del cobro.');
        }

        return DB::transaction(function () use ($cobId, $usuario, $motivo) {
            $cobro = Cobranza::whereKey($cobId)->lockForUpdate()->first();

            if (! $cobro) {
                throw new NegocioException('Cobro no encontrado.');
            }
            if ($cobro->cob_estado !== 'ACTIVA') {
                throw new NegocioException('Este cobro ya fue anulado.');
            }

            // 1. La deuda vuelve: se suma de nuevo lo pagado en cada cuenta del cobro.
            $detalles = DetalleCobranza::where('cob_id', $cobro->cob_id)->get();

            foreach ($detalles as $detalle) {
                $cuenta = CuentasCobrar::where('cred_id', $detalle->cred_id)->lockForUpdate()->first();

                if (! $cuenta) {
                    continue;
                }
                if ($cuenta->cred_estado === 'ANULADA') {
                    throw new NegocioException('La cuenta de este cobro fue anulada. Consultá con el responsable.');
                }

                // Nunca puede quedar más deuda que el total de la cuenta.
                $saldo = min(
                    round((float) $cuenta->cred_saldo_pendiente + (float) $detalle->det_monto_pagado, 2),
                    round((float) $cuenta->cred_monto_total, 2)
                );

                $cuenta->update(['cred_saldo_pendiente' => $saldo, 'cred_estado' => $saldo > 0 ? 'PENDIENTE' : 'PAGADA']);
            }

            // 2. El dinero sale de la caja, con la misma moneda y forma de pago con que entró.
            $neto = $this->caja->netoDeCobro($cobro->cob_id);
            $monto = $neto ? $neto['monto'] : round((float) $cobro->cob_monto_total, 2);
            $moneda = $neto ? $neto['moneda'] : 'GS';
            $forma = $neto ? $neto['forma'] : ($cobro->cob_formapago ?: 'EFECTIVO');

            if ($monto > 0) {
                $sesion = $this->sesionParaSalida($cobro, $usuario);

                $this->caja->registrar(
                    $sesion, 'EGRESO', $monto, $moneda,
                    'Anulación de cobro Nro. '.$cobro->cob_id.' ('.$forma.')',
                    $forma, ['cob_id' => $cobro->cob_id], permitirSaldoNegativo: true
                );
            }

            // 3. Estado, quién y por qué.
            $cobro->update([
                'cob_estado' => 'ANULADA',
                'cob_anulada_por' => $usuario->usu_id,
                'cob_anulada_fecha' => now(),
                'cob_motivo_anulacion' => mb_substr($motivo, 0, 255),
            ]);

            AuditoriaService::registrar('COBRO_ANULADO', 'cobranzas', $cobro->cob_id, [
                'monto' => (float) $cobro->cob_monto_total,
                'cliente' => $cobro->cli_id,
                'forma' => $forma,
                'motivo' => $cobro->cob_motivo_anulacion,
            ]);

            return $cobro;
        });
    }

    /** En la sesión original si sigue abierta; si no, en la caja abierta de quien anula. */
    private function sesionParaSalida(Cobranza $cobro, User $usuario): CajaSesion
    {
        $original = $cobro->ses_id ? CajaSesion::whereKey($cobro->ses_id)->lockForUpdate()->first() : null;

        if ($original && $original->ses_estado === 'ABIERTA') {
            return $original;
        }

        $propia = $this->caja->sesionAbiertaDe($usuario);

        if (! $propia) {
            throw new NegocioException('La caja donde se hizo el cobro ya está cerrada. Abrí tu caja para registrar la salida de dinero y volvé a intentar.');
        }

        return $propia;
    }
}
