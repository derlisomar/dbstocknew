<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\LicenciaPago;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Licencia del negocio: plan elegido, vencimiento, plazo de gracia y pagos.
 *
 * Estados (se calculan al momento, sin tareas programadas):
 *   SIN_LICENCIA : no se configuró ninguna licencia (sistemas anteriores): todo funciona como siempre.
 *   ACTIVA       : al día.
 *   POR_VENCER   : faltan pocos días; solo se muestra un aviso.
 *   GRACIA       : venció, pero está dentro de los días de gracia; sigue funcionando con aviso.
 *   SOLO_LECTURA : pasó la gracia (o el vendedor la suspendió): se puede consultar todo, pero no registrar nada.
 * Nunca se bloquea el acceso a los datos ni se pierde información.
 */
class LicenciaService
{
    public static function hoy(): Carbon
    {
        return Carbon::parse(now(config('vendedor.zona', 'America/Asuncion'))->toDateString());
    }

    /** @return array{estado:string, bloquea:bool, mensaje:?string, vence:?string, dias:?int, gracia_dias:int, tipo:?string} */
    public static function estado(): array
    {
        $tipo = ConfiguracionService::get('lic_tipo');
        $gracia = (int) ConfiguracionService::get('lic_gracia_dias', config('vendedor.gracia_dias', 5));
        $venceTexto = ConfiguracionService::get('lic_vence');
        $base = ['estado' => 'SIN_LICENCIA', 'bloquea' => false, 'mensaje' => null, 'vence' => $venceTexto, 'dias' => null, 'gracia_dias' => $gracia, 'tipo' => $tipo];

        if (ConfiguracionService::get('lic_suspendida') === '1') {
            return array_merge($base, [
                'estado' => 'SOLO_LECTURA', 'bloquea' => true,
                'mensaje' => 'El servicio está suspendido: podés consultar tu información pero no registrar operaciones. Comunicate con tu proveedor.',
            ]);
        }

        if (! $tipo) {
            return $base;
        }

        if ($tipo === 'PERPETUA' || ! $venceTexto) {
            return array_merge($base, ['estado' => 'ACTIVA']);
        }

        $dias = (int) self::hoy()->diffInDays(Carbon::parse($venceTexto), false); // negativo = ya venció
        $base['dias'] = $dias;

        if ($dias >= 0) {
            if ($dias <= (int) config('vendedor.aviso_dias', 7)) {
                return array_merge($base, [
                    'estado' => 'POR_VENCER',
                    'mensaje' => $dias === 0 ? 'Tu plan vence hoy.' : "Tu plan vence en {$dias} ".($dias === 1 ? 'día' : 'días').'. Renovalo para no interrumpir el servicio.',
                ]);
            }

            return array_merge($base, ['estado' => 'ACTIVA']);
        }

        $vencidoHace = abs($dias);
        if ($vencidoHace <= $gracia) {
            $quedan = $gracia - $vencidoHace;

            return array_merge($base, [
                'estado' => 'GRACIA',
                'mensaje' => "Tu plan venció. Regularizá el pago: te quedan {$quedan} ".($quedan === 1 ? 'día' : 'días').' antes de que el sistema pase a solo lectura.',
            ]);
        }

        return array_merge($base, [
            'estado' => 'SOLO_LECTURA', 'bloquea' => true,
            'mensaje' => 'Tu plan venció y terminó el plazo de gracia: el sistema está en solo lectura. Podés consultar todo, pero no registrar ventas ni movimientos hasta regularizar el pago.',
        ]);
    }

    // ------------------------------------------------------------------ planes

    /** Aplica un plan: edición, módulos, límites y tipo de licencia. No toca el vencimiento. */
    public static function aplicarPlan(int $planId): Plan
    {
        $plan = Plan::find($planId);
        if (! $plan) {
            throw new NegocioException('El plan elegido no existe.');
        }

        $modulos = array_values(array_unique(array_merge(
            ConfiguracionService::modulosDeEdicion($plan->plan_edicion),
            array_values(array_intersect($plan->modulosExtra(), array_keys(config('modulos.catalogo', []))))
        )));

        DB::transaction(function () use ($plan, $modulos) {
            ConfiguracionService::set([
                'edicion' => $plan->plan_edicion,
                'limite_sucursales' => (int) $plan->plan_max_sucursales ?: null,
                'limite_cajas' => (int) $plan->plan_max_cajas ?: null,
                'limite_usuarios' => (int) $plan->plan_max_usuarios ?: null,
                'lic_plan_id' => $plan->plan_id,
                'lic_tipo' => $plan->plan_meses > 0 ? 'SUSCRIPCION' : 'PERPETUA',
            ]);
            ConfiguracionService::fijarModulos($modulos);

            AuditoriaService::registrar('LICENCIA_PLAN', 'planes', $plan->plan_id, [
                'por' => 'vendedor', 'plan' => $plan->plan_nombre, 'edicion' => $plan->plan_edicion,
            ]);
        });

        return $plan;
    }

    // ------------------------------------------------------------------ pagos

    /**
     * Registra un pago del plan y corre el vencimiento.
     * Si se paga dentro del plazo de gracia, el período continúa desde el vencimiento anterior (no se pierden días);
     * si ya pasó la gracia, el nuevo período cuenta desde hoy.
     *
     * @param  array{fecha?:?string, monto:float|string, forma?:?string, referencia?:?string, plan_id?:?int, meses?:?int, hasta?:?string, nota?:?string}  $d
     */
    public static function registrarPago(array $d): LicenciaPago
    {
        $monto = (float) ($d['monto'] ?? 0);
        if ($monto < 0) {
            throw new NegocioException('El monto no puede ser negativo.');
        }

        $planId = ! empty($d['plan_id']) ? (int) $d['plan_id'] : (int) ConfiguracionService::get('lic_plan_id', 0);
        $plan = $planId ? Plan::find($planId) : null;
        $meses = isset($d['meses']) && $d['meses'] !== '' ? (int) $d['meses'] : (int) ($plan->plan_meses ?? 1);
        if ($meses < 0 || $meses > 60) {
            throw new NegocioException('Los meses que cubre el pago deben estar entre 0 y 60.');
        }

        $fecha = ! empty($d['fecha']) ? Carbon::parse($d['fecha'])->startOfDay() : self::hoy();

        return DB::transaction(function () use ($d, $monto, $planId, $plan, $meses, $fecha) {
            $hoy = self::hoy();
            $venceActual = ConfiguracionService::get('lic_vence');
            $tipoActual = ConfiguracionService::get('lic_tipo');
            $gracia = (int) ConfiguracionService::get('lic_gracia_dias', config('vendedor.gracia_dias', 5));

            $base = $hoy->copy();
            if ($venceActual) {
                $vence = Carbon::parse($venceActual);
                if ($hoy->lte($vence->copy()->addDays($gracia))) {
                    $base = $vence;
                }
            }

            $pagoUnico = $meses === 0;
            $hasta = null;
            if (! $pagoUnico) {
                $hasta = ! empty($d['hasta']) ? Carbon::parse($d['hasta'])->startOfDay() : $base->copy()->addMonthsNoOverflow($meses);
                if ($hasta->lte($base) && empty($d['hasta'])) {
                    throw new NegocioException('No se pudo calcular el nuevo vencimiento.');
                }
            }

            $pago = LicenciaPago::create([
                'plan_id' => $planId ?: null,
                'lpa_fecha' => $fecha->toDateString(),
                'lpa_monto' => $monto,
                'lpa_forma' => self::texto($d['forma'] ?? null, 30),
                'lpa_referencia' => self::texto($d['referencia'] ?? null, 120),
                'lpa_desde' => $base->toDateString(),
                'lpa_hasta' => $hasta?->toDateString(),
                'lpa_vence_anterior' => $venceActual,
                'lpa_tipo_anterior' => $tipoActual,
                'lpa_nota' => self::texto($d['nota'] ?? null, 255),
                'lpa_estado' => 'ACTIVO',
            ]);

            ConfiguracionService::set([
                'lic_tipo' => $pagoUnico ? 'PERPETUA' : 'SUSCRIPCION',
                'lic_vence' => $pagoUnico ? null : $hasta->toDateString(),
                'lic_suspendida' => null,
            ]);
            if ($planId && ! ConfiguracionService::get('lic_plan_id')) {
                ConfiguracionService::set(['lic_plan_id' => $planId]);
            }

            AuditoriaService::registrar('LICENCIA_PAGO', 'licencia_pagos', $pago->lpa_id, [
                'por' => 'vendedor', 'monto' => $monto, 'hasta' => $hasta?->toDateString(), 'plan' => $plan?->plan_nombre,
            ]);

            return $pago;
        });
    }

    /** Anula el último pago activo y devuelve el vencimiento a como estaba. */
    public static function anularPago(int $pagoId, string $motivo): LicenciaPago
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new NegocioException('Indicá el motivo de la anulación.');
        }

        return DB::transaction(function () use ($pagoId, $motivo) {
            $pago = LicenciaPago::whereKey($pagoId)->lockForUpdate()->first();
            if (! $pago) {
                throw new NegocioException('Pago no encontrado.');
            }
            if ($pago->lpa_estado !== 'ACTIVO') {
                throw new NegocioException('Ese pago ya está anulado.');
            }

            $ultimo = LicenciaPago::where('lpa_estado', 'ACTIVO')->max('lpa_id');
            if ((int) $ultimo !== (int) $pago->lpa_id) {
                throw new NegocioException('Solo se puede anular el último pago registrado. Anulá primero los más recientes.');
            }

            $pago->update(['lpa_estado' => 'ANULADO', 'lpa_anulado_fecha' => now(), 'lpa_motivo_anulacion' => mb_substr($motivo, 0, 255)]);

            ConfiguracionService::set([
                'lic_vence' => $pago->lpa_vence_anterior?->toDateString(),
                'lic_tipo' => $pago->lpa_tipo_anterior,
            ]);

            AuditoriaService::registrar('LICENCIA_PAGO_ANULAR', 'licencia_pagos', $pago->lpa_id, ['por' => 'vendedor', 'motivo' => $motivo]);

            return $pago;
        });
    }

    private static function texto($v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
