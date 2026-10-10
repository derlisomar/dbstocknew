<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Base del módulo contable: plan de cuentas, mapeos y creación de asientos.
 *
 * Reglas:
 *  - Un asiento SIEMPRE cuadra (debe = haber) y solo usa cuentas imputables y activas.
 *  - Los asientos automáticos llevan una "clave" única (ej. VENTA:15): nunca se contabiliza dos veces.
 *  - Un asiento no se edita ni se borra: se corrige con un contra-asiento.
 *  - Un período cerrado no admite asientos manuales; los automáticos pasan al primer día abierto.
 */
class ContabilidadService
{
    public const TIPOS = [
        'ACTIVO' => 'Activo',
        'PASIVO' => 'Pasivo',
        'PATRIMONIO' => 'Patrimonio neto',
        'INGRESO' => 'Ingresos',
        'EGRESO' => 'Costos y gastos',
    ];

    /** [código, nombre, tipo, imputable, clave del motor] */
    public const PLAN_BASE = [
        ['1', 'ACTIVO', 'ACTIVO', false, null],
        ['1.1', 'Activo corriente', 'ACTIVO', false, null],
        ['1.1.01', 'Caja en guaraníes', 'ACTIVO', true, 'caja_gs'],
        ['1.1.02', 'Caja en dólares', 'ACTIVO', true, 'caja_usd'],
        ['1.1.03', 'Caja en reales', 'ACTIVO', true, 'caja_brl'],
        ['1.1.04', 'Bancos', 'ACTIVO', true, 'banco'],
        ['1.1.05', 'Tarjetas y QR a acreditar', 'ACTIVO', true, 'tarjetas'],
        ['1.1.06', 'Deudores por ventas (clientes)', 'ACTIVO', true, 'clientes'],
        ['1.1.07', 'IVA crédito fiscal', 'ACTIVO', true, 'iva_credito'],
        ['1.1.08', 'Mercaderías', 'ACTIVO', true, 'mercaderias'],
        ['1.1.09', 'Cheques a depositar', 'ACTIVO', true, 'cheques'],
        ['2', 'PASIVO', 'PASIVO', false, null],
        ['2.1', 'Pasivo corriente', 'PASIVO', false, null],
        ['2.1.01', 'Proveedores', 'PASIVO', true, 'proveedores'],
        ['2.1.02', 'IVA débito fiscal', 'PASIVO', true, 'iva_debito'],
        ['2.1.03', 'Otras cuentas a pagar', 'PASIVO', true, 'otras_pagar'],
        ['3', 'PATRIMONIO NETO', 'PATRIMONIO', false, null],
        ['3.1.01', 'Capital', 'PATRIMONIO', true, 'capital'],
        ['3.1.02', 'Resultados acumulados', 'PATRIMONIO', true, 'resultados_acumulados'],
        ['4', 'INGRESOS', 'INGRESO', false, null],
        ['4.1.01', 'Ventas de mercaderías', 'INGRESO', true, 'ventas_mercaderias'],
        ['4.1.02', 'Ingresos por servicios', 'INGRESO', true, 'servicios'],
        ['4.2.01', 'Ganancia por diferencia de cambio', 'INGRESO', true, 'dif_cambio_ganancia'],
        ['4.2.02', 'Sobrante de caja', 'INGRESO', true, 'sobrante_caja'],
        ['4.2.03', 'Sobrante de inventario', 'INGRESO', true, 'sobrante_inventario'],
        ['4.2.04', 'Otros ingresos', 'INGRESO', true, 'otros_ingresos'],
        ['5', 'COSTOS Y GASTOS', 'EGRESO', false, null],
        ['5.1.01', 'Costo de mercaderías vendidas', 'EGRESO', true, 'cmv'],
        ['5.2.01', 'Pérdida por diferencia de cambio', 'EGRESO', true, 'dif_cambio_perdida'],
        ['5.2.02', 'Faltante de caja', 'EGRESO', true, 'faltante_caja'],
        ['5.2.03', 'Faltante de inventario (merma, robo, vencidos)', 'EGRESO', true, 'faltante_inventario'],
        ['5.2.04', 'Gastos generales', 'EGRESO', true, 'gastos_generales'],
        ['5.2.05', 'Comisiones y gastos bancarios', 'EGRESO', true, 'comisiones'],
        ['5.2.06', 'Sueldos y cargas sociales', 'EGRESO', true, null],
        ['5.2.07', 'Alquileres', 'EGRESO', true, null],
        ['5.2.08', 'Servicios básicos (luz, agua, internet)', 'EGRESO', true, null],
    ];

    /** Formas de cobro/pago y la cuenta que se usa si nadie la cambió. */
    public const PAGOS = [
        'EFECTIVO_GS' => ['Efectivo en guaraníes', 'caja_gs'],
        'EFECTIVO_USD' => ['Efectivo en dólares', 'caja_usd'],
        'EFECTIVO_BRL' => ['Efectivo en reales', 'caja_brl'],
        'TRANSFERENCIA' => ['Transferencia bancaria', 'banco'],
        'TARJETA_CREDITO' => ['Tarjeta de crédito', 'tarjetas'],
        'TARJETA_DEBITO' => ['Tarjeta de débito', 'tarjetas'],
        'QR' => ['Pago con QR', 'tarjetas'],
        'CHEQUE' => ['Cheque', 'cheques'],
        'TARJETA' => ['Tarjeta (pagos a proveedores)', 'banco'],
        'OTRO' => ['Otro medio de pago', 'caja_gs'],
    ];

    /** @var array<string, int> */
    private array $cacheClaves = [];

    /** @var array<string, int>|null */
    private ?array $cacheMapeos = null;

    // ------------------------------------------------------------------ estado del módulo

    public static function habilitada(): bool
    {
        return ConfiguracionService::modulo('contabilidad');
    }

    /** La contabilidad está lista cuando hay plan de cuentas y se eligió desde qué fecha se contabiliza. */
    public function preparada(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('cont_cuentas')
            && ConfiguracionService::get('cont_inicio') !== null
            && DB::table('cont_cuentas')->exists();
    }

    public function inicio(): ?Carbon
    {
        $v = ConfiguracionService::get('cont_inicio');

        return $v ? Carbon::parse($v)->startOfDay() : null;
    }

    public function cerradoHasta(): ?Carbon
    {
        $v = ConfiguracionService::get('cont_cerrado_hasta');

        return $v ? Carbon::parse($v)->startOfDay() : null;
    }

    /** Crea el plan base (solo las cuentas que faltan) y fija desde cuándo se contabiliza. */
    public function preparar(?string $inicio = null): int
    {
        $nuevas = $this->sembrarPlan();

        if (ConfiguracionService::get('cont_inicio') === null) {
            ConfiguracionService::set(['cont_inicio' => ($inicio ? Carbon::parse($inicio) : now())->toDateString()]);
        }

        return $nuevas;
    }

    public function sembrarPlan(): int
    {
        $creadas = 0;
        foreach (self::PLAN_BASE as [$codigo, $nombre, $tipo, $imputable, $clave]) {
            if (DB::table('cont_cuentas')->where('cue_codigo', $codigo)->exists()) {
                continue;
            }
            if ($clave && DB::table('cont_cuentas')->where('cue_clave', $clave)->exists()) {
                $clave = null;
            }
            DB::table('cont_cuentas')->insert([
                'cue_codigo' => $codigo, 'cue_nombre' => $nombre, 'cue_tipo' => $tipo,
                'cue_imputable' => $imputable, 'cue_activa' => true, 'cue_clave' => $clave,
            ]);
            $creadas++;
        }

        return $creadas;
    }

    public static function naturalezaDeudora(string $tipo): bool
    {
        return in_array($tipo, ['ACTIVO', 'EGRESO'], true);
    }

    // ------------------------------------------------------------------ cuentas y mapeos

    public function cuentaPorClave(string $clave): int
    {
        if (isset($this->cacheClaves[$clave])) {
            return $this->cacheClaves[$clave];
        }

        $id = DB::table('cont_cuentas')->where('cue_clave', $clave)->value('cue_id');
        if (! $id) {
            throw new NegocioException("Falta la cuenta contable «{$clave}». Entrá a Contabilidad > Plan de cuentas y creala o restaurá el plan base.");
        }

        return $this->cacheClaves[$clave] = (int) $id;
    }

    /** Clave de pago para una forma y moneda: el efectivo depende de la moneda. */
    public static function clavePago(?string $forma, string $moneda = 'GS'): string
    {
        $forma = strtoupper(trim((string) $forma)) ?: 'EFECTIVO';
        $moneda = strtoupper($moneda ?: 'GS');

        if ($forma === 'EFECTIVO') {
            return 'EFECTIVO_'.(in_array($moneda, ['GS', 'USD', 'BRL'], true) ? $moneda : 'GS');
        }

        return isset(self::PAGOS[$forma]) ? $forma : 'OTRO';
    }

    private function mapeos(): array
    {
        if ($this->cacheMapeos === null) {
            $this->cacheMapeos = [];
            foreach (DB::table('cont_mapeos')->get() as $m) {
                $this->cacheMapeos[$m->map_tipo.'|'.$m->map_clave] = (int) $m->cue_id;
            }
        }

        return $this->cacheMapeos;
    }

    public function olvidarCache(): void
    {
        $this->cacheClaves = [];
        $this->cacheMapeos = null;
    }

    public function cuentaDePago(?string $forma, string $moneda = 'GS'): int
    {
        $clave = self::clavePago($forma, $moneda);

        return $this->mapeos()['PAGO|'.$clave] ?? $this->cuentaPorClave(self::PAGOS[$clave][1]);
    }

    /** @param  'CAT_INGRESO'|'CAT_INVENTARIO'|'CAT_COSTO'  $tipo */
    public function cuentaDeCategoria(string $tipo, ?int $catId): int
    {
        if ($catId !== null) {
            $m = $this->mapeos()[$tipo.'|'.$catId] ?? null;
            if ($m) {
                return $m;
            }
        }

        return $this->cuentaPorClave(match ($tipo) {
            'CAT_INGRESO' => 'ventas_mercaderias',
            'CAT_INVENTARIO' => 'mercaderias',
            default => 'cmv',
        });
    }

    // ------------------------------------------------------------------ cotización

    /** Guaraníes por 1 unidad de la moneda, según la cotización vigente en esa fecha. */
    public function tasa(string $moneda, ?Carbon $fecha = null): float
    {
        $moneda = strtoupper($moneda);
        if ($moneda === 'GS') {
            return 1.0;
        }

        $col = $moneda === 'USD' ? 'cot_dolar' : 'cot_real';
        $q = DB::table('cotizaciones');
        if ($fecha) {
            $q->whereDate('cot_fecha', '<=', $fecha->toDateString());
        }
        $valor = (clone $q)->orderByDesc('cot_fecha')->orderByDesc('cot_id')->value($col);

        if ($valor === null) {
            $valor = DB::table('cotizaciones')->where('cot_activa', true)->orderByDesc('cot_id')->value($col);
        }

        return (float) ($valor ?: ($moneda === 'USD' ? 7500 : 1500));
    }

    // ------------------------------------------------------------------ asientos

    /**
     * @param  list<array{0:int,1:float,2:float,3?:?string}>  $lineas  [cue_id, debe, haber, detalle]
     * @return int|null  id del asiento (si la clave ya existía devuelve el existente)
     */
    public function crearAsiento(
        string $fecha,
        string $glosa,
        string $origen,
        ?string $clave,
        array $lineas,
        ?int $usuId = null,
        bool $manual = false,
        ?int $revierteId = null
    ): ?int {
        if ($clave) {
            $existe = DB::table('cont_asientos')->where('asi_clave', $clave)->value('asi_id');
            if ($existe) {
                return (int) $existe;
            }
        }

        // Limpia líneas vacías y junta debe/haber.
        $limpias = [];
        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $l) {
            $d = round((float) $l[1], 2);
            $h = round((float) $l[2], 2);
            if ($d < 0 || $h < 0) {
                throw new NegocioException('Los importes de un asiento no pueden ser negativos.');
            }
            if ($d == 0.0 && $h == 0.0) {
                continue;
            }
            if ($d > 0 && $h > 0) {
                throw new NegocioException('Una línea no puede tener importe en el debe y en el haber a la vez.');
            }
            $limpias[] = [(int) $l[0], $d, $h, isset($l[3]) ? mb_substr((string) $l[3], 0, 160) : null];
            $debe += $d;
            $haber += $h;
        }

        if (count($limpias) < 2) {
            throw new NegocioException('Un asiento necesita al menos dos líneas con importe.');
        }
        if (abs(round($debe, 2) - round($haber, 2)) >= 0.005) {
            throw new NegocioException('El asiento no cuadra: debe '.number_format($debe, 2, ',', '.').' y haber '.number_format($haber, 2, ',', '.').'.');
        }

        $ids = array_unique(array_column($limpias, 0));
        $validas = DB::table('cont_cuentas')->whereIn('cue_id', $ids)->where('cue_imputable', true)->where('cue_activa', true)->count();
        if ($validas !== count($ids)) {
            throw new NegocioException('Hay una cuenta que no existe, está inactiva o es una cuenta título (no recibe asientos).');
        }

        $f = Carbon::parse($fecha)->startOfDay();
        $cierre = $this->cerradoHasta();
        if ($cierre && $f->lte($cierre)) {
            if ($manual) {
                throw new NegocioException('El período hasta el '.$cierre->format('d/m/Y').' está cerrado: no se pueden cargar asientos en esa fecha.');
            }
            $glosa .= ' (fecha original '.$f->format('d/m/Y').')';
            $f = $cierre->copy()->addDay();
        }

        $intentos = 0;
        while (true) {
            try {
                return DB::transaction(function () use ($f, $glosa, $origen, $clave, $limpias, $usuId, $revierteId) {
                    $numero = ((int) DB::table('cont_asientos')->max('asi_numero')) + 1;
                    $id = DB::table('cont_asientos')->insertGetId([
                        'asi_numero' => $numero,
                        'asi_fecha' => $f->toDateString(),
                        'asi_glosa' => mb_substr($glosa, 0, 255),
                        'asi_origen' => $origen,
                        'asi_clave' => $clave,
                        'asi_estado' => 'VIGENTE',
                        'usu_id' => $usuId,
                        'asi_revierte_id' => $revierteId,
                    ], 'asi_id');

                    foreach ($limpias as [$cue, $d, $h, $det]) {
                        DB::table('cont_lineas')->insert([
                            'asi_id' => $id, 'cue_id' => $cue, 'lin_debe' => $d, 'lin_haber' => $h, 'lin_detalle' => $det,
                        ]);
                    }

                    return (int) $id;
                });
            } catch (QueryException $e) {
                // Dos procesos tomaron el mismo número a la vez: se reintenta con el siguiente.
                if (++$intentos >= 4) {
                    throw $e;
                }
                if ($clave && DB::table('cont_asientos')->where('asi_clave', $clave)->exists()) {
                    return (int) DB::table('cont_asientos')->where('asi_clave', $clave)->value('asi_id');
                }
            }
        }
    }

    /** Contra-asiento: las mismas líneas con debe y haber invertidos. */
    public function reversar(int $asiId, ?string $clave, string $fecha, string $glosa, string $origen, ?int $usuId = null, bool $manual = false): ?int
    {
        $lineas = DB::table('cont_lineas')->where('asi_id', $asiId)->orderBy('lin_id')->get();
        if ($lineas->isEmpty()) {
            return null;
        }

        $nuevo = $this->crearAsiento(
            $fecha, $glosa, $origen, $clave,
            $lineas->map(fn ($l) => [$l->cue_id, $l->lin_haber, $l->lin_debe, $l->lin_detalle])->all(),
            $usuId, $manual, $asiId
        );

        DB::table('cont_asientos')->where('asi_id', $asiId)->update(['asi_estado' => 'ANULADO']);

        return $nuevo;
    }

    /** Anula un asiento MANUAL con un contra-asiento. */
    public function anularManual(int $asiId, string $motivo, ?int $usuId): int
    {
        $a = DB::table('cont_asientos')->where('asi_id', $asiId)->first();
        if (! $a || $a->asi_origen !== 'MANUAL') {
            throw new NegocioException('Solo se anulan desde acá los asientos manuales; los automáticos se corrigen anulando la operación que los generó.');
        }
        if ($a->asi_estado === 'ANULADO') {
            throw new NegocioException('Ese asiento ya está anulado.');
        }

        $id = $this->reversar(
            $asiId, 'MANUAL_ANUL:'.$asiId, now()->toDateString(),
            'Anulación del asiento N° '.$a->asi_numero.': '.mb_substr($motivo, 0, 150), 'MANUAL', $usuId, true
        );

        AuditoriaService::registrar('ASIENTO_ANULADO', 'cont_asientos', $asiId, ['motivo' => $motivo]);

        return (int) $id;
    }
}
