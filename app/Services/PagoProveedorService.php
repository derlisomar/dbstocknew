<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CajaSesion;
use App\Models\CuentaPagar;
use App\Models\PagoProveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pagos a proveedores sobre una cuenta a pagar.
 *
 * Regla de caja: solo el pago en EFECTIVO sale de la caja (queda en el libro y baja el efectivo del cajón).
 * Transferencia, cheque, tarjeta u otro se registran como pago pero no mueven el efectivo.
 */
class PagoProveedorService
{
    public const FORMAS = ['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'TARJETA', 'OTRO'];

    public function __construct(private CajaService $caja)
    {
    }

    public function pagar(int $cpaId, float $monto, string $forma, ?string $referencia, User $usuario, ?int $cajId = null): PagoProveedor
    {
        $forma = strtoupper($forma);
        $monto = round($monto, 2);

        if (! in_array($forma, self::FORMAS, true)) {
            throw new NegocioException('Forma de pago no válida.');
        }
        if ($monto <= 0) {
            throw new NegocioException('El monto debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($cpaId, $monto, $forma, $referencia, $usuario, $cajId) {
            $cuenta = CuentaPagar::whereKey($cpaId)->lockForUpdate()->first();

            if (! $cuenta || $cuenta->cpa_estado !== 'PENDIENTE') {
                throw new NegocioException('Esta cuenta ya no tiene deuda pendiente.');
            }
            if ($monto > round((float) $cuenta->cpa_saldo_pendiente, 2)) {
                throw new NegocioException('El monto supera la deuda actual con el proveedor.');
            }

            $sesion = null;
            if ($forma === 'EFECTIVO') {
                $sesion = $this->caja->sesionAbiertaDe($usuario, $cajId);
                if (! $sesion) {
                    throw new NegocioException('Para pagar en efectivo necesitás tener una caja abierta.');
                }
            }

            $pago = PagoProveedor::create([
                'cpa_id' => $cuenta->cpa_id,
                'prov_id' => $cuenta->prov_id,
                'usu_id' => $usuario->usu_id,
                'ses_id' => $sesion?->ses_id,
                'pag_fecha' => now(),
                'pag_monto' => $monto,
                'pag_forma_pago' => $forma,
                'pag_referencia' => $referencia !== null ? mb_substr(trim($referencia), 0, 120) : null,
                'pag_estado' => 'ACTIVO',
            ]);

            if ($sesion) {
                $this->caja->registrar(
                    $sesion, 'EGRESO', $monto, 'GS',
                    'Pago a proveedor - Compra Nro '.$cuenta->com_id.' (pago '.$pago->pag_id.')',
                    'EFECTIVO', ['pag_id' => $pago->pag_id]
                );
            }

            $saldo = round((float) $cuenta->cpa_saldo_pendiente - $monto, 2);
            $cuenta->update(['cpa_saldo_pendiente' => $saldo, 'cpa_estado' => $saldo <= 0 ? 'PAGADA' : 'PENDIENTE']);

            AuditoriaService::registrar('PAGO_PROVEEDOR', 'pagos_proveedores', $pago->pag_id, [
                'cuenta' => $cuenta->cpa_id, 'compra' => $cuenta->com_id, 'proveedor' => $cuenta->prov_id,
                'monto' => $monto, 'forma' => $forma, 'saldo_restante' => $saldo,
            ]);

            return $pago;
        });
    }

    /**
     * Anula un pago: la deuda vuelve y, si había salido efectivo, el dinero vuelve a la caja.
     *
     * @param  bool  $restaurarCuenta  false cuando la propia cuenta se está anulando (anulación de la compra)
     */
    public function anular(int $pagId, User $usuario, string $motivo, bool $restaurarCuenta = true): PagoProveedor
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new NegocioException('Indicá el motivo de la anulación del pago.');
        }

        return DB::transaction(function () use ($pagId, $usuario, $motivo, $restaurarCuenta) {
            $pago = PagoProveedor::whereKey($pagId)->lockForUpdate()->first();

            if (! $pago) {
                throw new NegocioException('Pago no encontrado.');
            }
            if ($pago->pag_estado !== 'ACTIVO') {
                throw new NegocioException('Este pago ya fue anulado.');
            }

            if ($restaurarCuenta) {
                $cuenta = CuentaPagar::whereKey($pago->cpa_id)->lockForUpdate()->first();
                if (! $cuenta || $cuenta->cpa_estado === 'ANULADA') {
                    throw new NegocioException('La cuenta de este pago fue anulada.');
                }

                $saldo = min(
                    round((float) $cuenta->cpa_saldo_pendiente + (float) $pago->pag_monto, 2),
                    round((float) $cuenta->cpa_monto_total, 2)
                );
                $cuenta->update(['cpa_saldo_pendiente' => $saldo, 'cpa_estado' => $saldo > 0 ? 'PENDIENTE' : 'PAGADA']);
            }

            if ($pago->pag_forma_pago === 'EFECTIVO' && $pago->ses_id) {
                $sesion = $this->sesionParaReintegro($pago, $usuario);

                $this->caja->registrar(
                    $sesion, 'INGRESO', (float) $pago->pag_monto, 'GS',
                    'Anulación de pago a proveedor Nro '.$pago->pag_id,
                    'EFECTIVO', ['pag_id' => $pago->pag_id]
                );
            }

            $pago->update([
                'pag_estado' => 'ANULADO',
                'pag_anulado_por' => $usuario->usu_id,
                'pag_anulado_fecha' => now(),
                'pag_motivo_anulacion' => mb_substr($motivo, 0, 255),
            ]);

            AuditoriaService::registrar('PAGO_PROVEEDOR_ANULADO', 'pagos_proveedores', $pago->pag_id, [
                'monto' => (float) $pago->pag_monto, 'forma' => $pago->pag_forma_pago, 'motivo' => $motivo,
            ]);

            return $pago;
        });
    }

    private function sesionParaReintegro(PagoProveedor $pago, User $usuario): CajaSesion
    {
        $original = CajaSesion::whereKey($pago->ses_id)->lockForUpdate()->first();
        if ($original && $original->ses_estado === 'ABIERTA') {
            return $original;
        }

        $propia = $this->caja->sesionAbiertaDe($usuario);
        if (! $propia) {
            throw new NegocioException('La caja de donde salió el efectivo ya está cerrada. Abrí tu caja para registrar el reintegro y volvé a intentar.');
        }

        return $propia;
    }
}
