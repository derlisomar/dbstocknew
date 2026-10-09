<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Presupuestos a clientes. Un presupuesto no toca stock ni caja: solo guarda lo que se ofreció,
 * a qué precio y hasta cuándo vale. El stock y la caja se mueven recién cuando se convierte en venta
 * desde el punto de venta (ahí se vuelve a controlar stock, precio y crédito).
 */
class PresupuestoService
{
    public function __construct(private PrecioService $precios)
    {
    }

    /**
     * @param  array{cli_id?:?int, cliente_nombre?:?string, validez_dias?:?int, observacion?:?string, condiciones?:?string,
     *               items:array<int,array{pro_id:int,cantidad:float|string}>}  $d
     */
    public function crear(array $d, User $usuario): Presupuesto
    {
        $dias = $this->validezValida($d['validez_dias'] ?? null);
        [$cliId, $nombreLibre, $esMayorista] = $this->resolverCliente($d);
        $lineas = $this->juntarLineas($d['items'] ?? []);

        return DB::transaction(function () use ($d, $usuario, $dias, $cliId, $nombreLibre, $esMayorista, $lineas) {
            [$detalle, $total] = $this->cotizar($lineas, $esMayorista);

            $presupuesto = Presupuesto::create([
                'cli_id' => $cliId,
                'pre_cliente_nombre' => $nombreLibre,
                'usu_id' => $usuario->usu_id,
                'suc_id' => $usuario->suc_id ?? null,
                'pre_fecha' => now(),
                'pre_validez_dias' => $dias,
                'pre_fecha_vencimiento' => Carbon::parse(Presupuesto::hoy())->addDays($dias)->toDateString(),
                'pre_total' => $total,
                'pre_estado' => 'BORRADOR',
                'pre_observacion' => $this->texto($d['observacion'] ?? null, 255),
                'pre_condiciones' => $this->texto($d['condiciones'] ?? null, 500),
            ]);

            $this->guardarDetalle($presupuesto, $detalle);

            AuditoriaService::registrar('PRESUPUESTO_CREAR', 'presupuestos', $presupuesto->pre_id, [
                'total' => $total, 'validez_dias' => $dias, 'items' => count($detalle),
            ]);

            return $presupuesto;
        });
    }

    /** Edita un presupuesto que todavía no fue aceptado ni convertido en venta. */
    public function actualizar(int $id, array $d, User $usuario): Presupuesto
    {
        $dias = $this->validezValida($d['validez_dias'] ?? null);
        [$cliId, $nombreLibre, $esMayorista] = $this->resolverCliente($d);
        $lineas = $this->juntarLineas($d['items'] ?? []);

        return DB::transaction(function () use ($id, $d, $dias, $cliId, $nombreLibre, $esMayorista, $lineas) {
            $p = Presupuesto::whereKey($id)->lockForUpdate()->first();
            if (! $p) {
                throw new NegocioException('Presupuesto no encontrado.');
            }
            if (! in_array($p->pre_estado, ['BORRADOR', 'ENVIADO'], true)) {
                throw new NegocioException('Solo se pueden editar presupuestos en borrador o enviados. Si ya fue aceptado, rechazalo y armá uno nuevo.');
            }

            // La validez se cuenta desde el día en que se armó el presupuesto.
            $vence = Carbon::parse($p->pre_fecha->copy()->setTimezone(config('presupuestos.zona'))->toDateString())->addDays($dias);
            if ($vence->toDateString() < Presupuesto::hoy()) {
                throw new NegocioException('Con esa validez el presupuesto quedaría vencido. Usá "Renovar validez" para darle nuevos días desde hoy.');
            }

            [$detalle, $total] = $this->cotizar($lineas, $esMayorista);

            $p->update([
                'cli_id' => $cliId,
                'pre_cliente_nombre' => $nombreLibre,
                'pre_validez_dias' => $dias,
                'pre_fecha_vencimiento' => $vence->toDateString(),
                'pre_total' => $total,
                'pre_observacion' => $this->texto($d['observacion'] ?? null, 255),
                'pre_condiciones' => $this->texto($d['condiciones'] ?? null, 500),
            ]);

            DetallePresupuesto::where('pre_id', $p->pre_id)->delete();
            $this->guardarDetalle($p, $detalle);

            AuditoriaService::registrar('PRESUPUESTO_EDITAR', 'presupuestos', $p->pre_id, ['total' => $total, 'validez_dias' => $dias]);

            return $p;
        });
    }

    /** Marca el presupuesto como ENVIADO, ACEPTADO, RECHAZADO o lo reabre como BORRADOR. */
    public function cambiarEstado(int $id, string $nuevo, User $usuario, ?string $motivo = null): Presupuesto
    {
        $nuevo = strtoupper($nuevo);

        return DB::transaction(function () use ($id, $nuevo, $motivo) {
            $p = Presupuesto::whereKey($id)->lockForUpdate()->first();
            if (! $p) {
                throw new NegocioException('Presupuesto no encontrado.');
            }
            if ($p->pre_estado === 'FACTURADO') {
                throw new NegocioException('Este presupuesto ya se convirtió en venta y no se puede modificar.');
            }

            $permitidas = [
                'ENVIADO' => ['BORRADOR'],
                'ACEPTADO' => ['BORRADOR', 'ENVIADO'],
                'RECHAZADO' => ['BORRADOR', 'ENVIADO', 'ACEPTADO'],
                'BORRADOR' => ['RECHAZADO'],
            ];
            if (! isset($permitidas[$nuevo]) || ! in_array($p->pre_estado, $permitidas[$nuevo], true)) {
                throw new NegocioException('Ese cambio de estado no está permitido desde «'.$p->pre_estado.'».');
            }

            if (in_array($nuevo, ['ENVIADO', 'ACEPTADO', 'BORRADOR'], true) && $p->pre_fecha_vencimiento->toDateString() < Presupuesto::hoy()) {
                throw new NegocioException('El presupuesto está vencido. Renová la validez antes de continuar.');
            }

            $datos = ['pre_estado' => $nuevo];
            if ($nuevo === 'ACEPTADO') {
                $datos['pre_aceptado_fecha'] = now();
                $datos['pre_rechazo_motivo'] = null;
            }
            if ($nuevo === 'RECHAZADO') {
                $datos['pre_rechazo_motivo'] = $this->texto($motivo, 255);
            }
            if ($nuevo === 'BORRADOR') {
                $datos['pre_rechazo_motivo'] = null;
                $datos['pre_aceptado_fecha'] = null;
            }

            $anterior = $p->pre_estado;
            $p->update($datos);

            AuditoriaService::registrar('PRESUPUESTO_'.$nuevo, 'presupuestos', $p->pre_id, ['antes' => $anterior, 'motivo' => $motivo]);

            return $p;
        });
    }

    /** Da nuevos días de validez contando desde hoy (para presupuestos vencidos que el cliente retoma). */
    public function renovar(int $id, ?int $dias, User $usuario): Presupuesto
    {
        $dias = $this->validezValida($dias);

        return DB::transaction(function () use ($id, $dias) {
            $p = Presupuesto::whereKey($id)->lockForUpdate()->first();
            if (! $p) {
                throw new NegocioException('Presupuesto no encontrado.');
            }
            if (! $p->estaAbierto()) {
                throw new NegocioException('Solo se puede renovar un presupuesto abierto (borrador, enviado o aceptado).');
            }

            $anterior = $p->pre_fecha_vencimiento->toDateString();
            $p->update([
                'pre_validez_dias' => $dias,
                'pre_fecha_vencimiento' => Carbon::parse(Presupuesto::hoy())->addDays($dias)->toDateString(),
            ]);

            AuditoriaService::registrar('PRESUPUESTO_RENOVAR', 'presupuestos', $p->pre_id, [
                'vencimiento_anterior' => $anterior, 'nuevo_vencimiento' => $p->pre_fecha_vencimiento->toDateString(),
            ]);

            return $p;
        });
    }

    /**
     * Verifica que el presupuesto se pueda convertir en venta. Lanza NegocioException con el motivo si no.
     */
    public function verificarConvertible(Presupuesto $p): void
    {
        if ($p->pre_estado === 'FACTURADO') {
            throw new NegocioException("El presupuesto {$p->numero} ya se convirtió en venta.");
        }
        if ($p->pre_estado === 'RECHAZADO') {
            throw new NegocioException("El presupuesto {$p->numero} fue rechazado. Reabrilo para poder venderlo.");
        }
        if ($p->estaVencido()) {
            throw new NegocioException("El presupuesto {$p->numero} venció el {$p->pre_fecha_vencimiento->format('d/m/Y')}. Renová la validez para poder venderlo.");
        }
    }

    /**
     * Se llama DENTRO de la transacción de la venta: así el presupuesto queda facturado solo si la venta se guardó,
     * y nadie puede vender dos veces el mismo presupuesto.
     */
    public function marcarFacturado(int $id, int $ventaId): Presupuesto
    {
        $p = Presupuesto::whereKey($id)->lockForUpdate()->first();
        if (! $p) {
            throw new NegocioException('El presupuesto que se quiere facturar ya no existe.');
        }

        $this->verificarConvertible($p);

        $p->update(['pre_estado' => 'FACTURADO', 'vta_id' => $ventaId, 'pre_facturado_fecha' => now()]);

        AuditoriaService::registrar('PRESUPUESTO_FACTURAR', 'presupuestos', $p->pre_id, ['vta_id' => $ventaId]);

        return $p;
    }

    // ------------------------------------------------------------------ internos

    private function validezValida($dias): int
    {
        $dias = $dias === null || $dias === '' ? (int) config('presupuestos.validez_defecto_dias', 7) : (int) $dias;
        $max = (int) config('presupuestos.validez_maxima_dias', 90);

        if ($dias < 1 || $dias > $max) {
            throw new NegocioException("La validez debe estar entre 1 y {$max} días.");
        }

        return $dias;
    }

    /** @return array{0:?int, 1:?string, 2:bool} cli_id, nombre libre, si el cliente es mayorista */
    private function resolverCliente(array $d): array
    {
        if (! empty($d['cli_id'])) {
            $cliente = Cliente::find($d['cli_id']);
            if (! $cliente) {
                throw new NegocioException('El cliente elegido no existe.');
            }

            return [(int) $cliente->cli_id, null, (bool) $cliente->cli_es_mayorista];
        }

        $nombre = $this->texto($d['cliente_nombre'] ?? null, 120);
        if ($nombre === null) {
            throw new NegocioException('Elegí un cliente o escribí el nombre de a quién va dirigido el presupuesto.');
        }

        return [null, $nombre, false];
    }

    /** @return array<int,float> pro_id => cantidad (si un producto viene repetido, se suman) */
    private function juntarLineas(array $items): array
    {
        $lineas = [];
        foreach ($items as $it) {
            $proId = (int) ($it['pro_id'] ?? 0);
            $cant = (float) ($it['cantidad'] ?? 0);
            if ($proId <= 0 || $cant <= 0) {
                continue;
            }
            $lineas[$proId] = ($lineas[$proId] ?? 0) + $cant;
        }

        if ($lineas === []) {
            throw new NegocioException('Agregá al menos un producto al presupuesto.');
        }

        return $lineas;
    }

    /** Precios los calcula el servidor con las mismas reglas del punto de venta (mayorista y promociones). */
    private function cotizar(array $lineas, bool $esMayorista): array
    {
        $productos = Producto::whereIn('pro_id', array_keys($lineas))->get()->keyBy('pro_id');
        $promos = $this->precios->promocionesVigentes();
        $detalle = [];
        $total = 0.0;

        foreach ($lineas as $proId => $cantidad) {
            $producto = $productos->get($proId);
            if (! $producto || ! $producto->pro_activo) {
                throw new NegocioException('Uno de los productos no existe o está inactivo.');
            }

            $precio = $this->precios->precioFinal($producto, $esMayorista, $promos);
            $subtotal = round($precio * $cantidad, 2);
            $total += $subtotal;
            $detalle[] = ['pro_id' => (int) $proId, 'cantidad' => $cantidad, 'precio' => $precio, 'subtotal' => $subtotal];
        }

        return [$detalle, round($total, 2)];
    }

    private function guardarDetalle(Presupuesto $p, array $detalle): void
    {
        foreach ($detalle as $l) {
            DetallePresupuesto::create([
                'pre_id' => $p->pre_id,
                'pro_id' => $l['pro_id'],
                'dpr_cantidad' => $l['cantidad'],
                'dpr_precio' => $l['precio'],
                'dpr_subtotal' => $l['subtotal'],
            ]);
        }
    }

    private function texto($valor, int $max): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }
}
