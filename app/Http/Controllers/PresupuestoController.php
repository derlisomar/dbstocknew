<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Services\PrecioService;
use App\Services\PresupuestoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PresupuestoController extends Controller
{
    /** Listado con pestañas: vigentes, vencidos, por vender, todos. */
    public function index(Request $request)
    {
        $f = $request->validate([
            'vista' => ['nullable', 'in:vigentes,vencidos,por_vender,rechazados,facturados,todos'],
            'q' => ['nullable', 'string', 'max:60'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);
        $vista = $f['vista'] ?? 'vigentes';

        $base = Presupuesto::query();
        if (! empty($f['q'])) {
            // LOWER(...) para que no importe mayúsculas/minúsculas ni en PostgreSQL ni en SQLite.
            $like = '%'.mb_strtolower(addcslashes($f['q'], '%_\\')).'%';
            $nro = ltrim(preg_replace('/\D/', '', $f['q']), '0');
            $base->where(function ($w) use ($like, $nro) {
                $w->whereRaw('LOWER(pre_cliente_nombre) LIKE ?', [$like])
                    ->orWhereIn('cli_id', Cliente::whereRaw('LOWER(cli_nombre) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cli_apellido) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cli_ruc_ci) LIKE ?', [$like])->pluck('cli_id'));
                if ($nro !== '') {
                    $w->orWhere('pre_id', (int) $nro);
                }
            });
        }
        if (! empty($f['desde'])) {
            $base->whereDate('pre_fecha', '>=', $f['desde']);
        }
        if (! empty($f['hasta'])) {
            $base->whereDate('pre_fecha', '<=', $f['hasta']);
        }

        // Contadores y montos de cada pestaña (con el mismo texto de búsqueda y fechas).
        $resumen = [];
        foreach ([
            'vigentes' => fn ($q) => $q->vigentes(),
            'por_vender' => fn ($q) => $q->porVender(),
            'vencidos' => fn ($q) => $q->vencidos(),
            'rechazados' => fn ($q) => $q->where('pre_estado', 'RECHAZADO'),
            'facturados' => fn ($q) => $q->where('pre_estado', 'FACTURADO'),
            'todos' => fn ($q) => $q,
        ] as $clave => $filtro) {
            $q = $filtro(clone $base);
            $resumen[$clave] = ['cantidad' => (clone $q)->count(), 'monto' => (float) (clone $q)->sum('pre_total')];
        }

        $consulta = clone $base;
        match ($vista) {
            'vigentes' => $consulta->vigentes(),
            'por_vender' => $consulta->porVender(),
            'vencidos' => $consulta->vencidos(),
            'rechazados' => $consulta->where('pre_estado', 'RECHAZADO'),
            'facturados' => $consulta->where('pre_estado', 'FACTURADO'),
            default => $consulta,
        };

        // Los que vencen antes, primero (lo más urgente arriba); en el resto, lo más nuevo primero.
        if (in_array($vista, ['vigentes', 'por_vender'], true)) {
            $consulta->orderBy('pre_fecha_vencimiento')->orderBy('pre_id');
        } else {
            $consulta->orderByDesc('pre_fecha')->orderByDesc('pre_id');
        }

        return view('presupuestos.index', [
            'presupuestos' => $consulta->with(['cliente', 'usuario'])->paginate(25)->withQueryString(),
            'vista' => $vista,
            'resumen' => $resumen,
            'avisoDias' => (int) config('presupuestos.aviso_dias', 2),
        ]);
    }

    public function create()
    {
        return view('presupuestos.form', $this->datosFormulario(null));
    }

    public function store(Request $request, PresupuestoService $servicio)
    {
        $datos = $this->validar($request);

        try {
            $p = $servicio->crear($datos, $request->user());
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->errorInesperado('crear el presupuesto', $e));
        }

        return redirect()->route('presupuestos.show', $p->pre_id)->with('success', 'Presupuesto creado. No se descontó stock: eso pasa recién al convertirlo en venta.');
    }

    public function edit($id)
    {
        $p = Presupuesto::with('detalles')->findOrFail($id);
        if (! in_array($p->pre_estado, ['BORRADOR', 'ENVIADO'], true)) {
            return redirect()->route('presupuestos.show', $p->pre_id)->with('error', 'Solo se pueden editar presupuestos en borrador o enviados.');
        }

        return view('presupuestos.form', $this->datosFormulario($p));
    }

    public function update(Request $request, $id, PresupuestoService $servicio)
    {
        $datos = $this->validar($request);

        try {
            $p = $servicio->actualizar((int) $id, $datos, $request->user());
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $this->errorInesperado('guardar el presupuesto', $e));
        }

        return redirect()->route('presupuestos.show', $p->pre_id)->with('success', 'Presupuesto actualizado.');
    }

    public function show($id, PresupuestoService $servicio)
    {
        $p = Presupuesto::with(['cliente', 'usuario', 'venta', 'detalles.producto'])->findOrFail($id);

        $convertible = true;
        $motivoNoConvertible = null;
        try {
            $servicio->verificarConvertible($p);
        } catch (NegocioException $e) {
            $convertible = false;
            $motivoNoConvertible = $e->getMessage();
        }

        // Aviso si algún precio cambió desde que se cotizó (la venta usa el precio vigente al vender).
        $cambios = [];
        if ($p->estaAbierto()) {
            $precios = app(PrecioService::class);
            $promos = $precios->promocionesVigentes();
            $mayorista = (bool) ($p->cliente->cli_es_mayorista ?? false);
            foreach ($p->detalles as $d) {
                if ($d->producto) {
                    $actual = $precios->precioFinal($d->producto, $mayorista, $promos);
                    if (abs($actual - (float) $d->dpr_precio) >= 0.01) {
                        $cambios[$d->pro_id] = $actual;
                    }
                }
            }
        }

        return view('presupuestos.show', compact('p', 'convertible', 'motivoNoConvertible', 'cambios'));
    }

    public function imprimir($id)
    {
        $p = Presupuesto::with(['cliente', 'usuario', 'detalles.producto'])->findOrFail($id);

        return view('presupuestos.imprimir', compact('p'));
    }

    public function estado(Request $request, $id, PresupuestoService $servicio)
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:BORRADOR,ENVIADO,ACEPTADO,RECHAZADO'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $servicio->cambiarEstado((int) $id, $datos['estado'], $request->user(), $datos['motivo'] ?? null);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', $this->errorInesperado('cambiar el estado', $e));
        }

        $msg = [
            'ENVIADO' => 'Presupuesto marcado como enviado.',
            'ACEPTADO' => 'Presupuesto aceptado: ya aparece en "Por vender". El stock no se toca hasta convertirlo en venta.',
            'RECHAZADO' => 'Presupuesto rechazado.',
            'BORRADOR' => 'Presupuesto reabierto como borrador.',
        ][$datos['estado']];

        return back()->with('success', $msg);
    }

    public function renovar(Request $request, $id, PresupuestoService $servicio)
    {
        $datos = $request->validate(['validez_dias' => ['required', 'integer', 'min:1', 'max:'.(int) config('presupuestos.validez_maxima_dias', 90)]]);

        try {
            $p = $servicio->renovar((int) $id, (int) $datos['validez_dias'], $request->user());
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', $this->errorInesperado('renovar el presupuesto', $e));
        }

        return back()->with('success', 'Validez renovada: ahora vale hasta el '.$p->pre_fecha_vencimiento->format('d/m/Y').'.');
    }

    // ------------------------------------------------------------------ internos

    private function validar(Request $request): array
    {
        $max = (int) config('presupuestos.validez_maxima_dias', 90);

        return $request->validate([
            'cli_id' => ['nullable', 'integer', 'exists:clientes,cli_id'],
            'cliente_nombre' => ['nullable', 'string', 'max:120'],
            'validez_dias' => ['required', 'integer', 'min:1', 'max:'.$max],
            'observacion' => ['nullable', 'string', 'max:255'],
            'condiciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.pro_id' => ['required', 'integer', 'exists:productos,pro_id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ], [
            'items.required' => 'Agregá al menos un producto al presupuesto.',
            'items.min' => 'Agregá al menos un producto al presupuesto.',
            'validez_dias.required' => 'Indicá cuántos días es válido el presupuesto.',
            'validez_dias.min' => 'La validez mínima es de 1 día.',
            'validez_dias.max' => "La validez máxima es de {$max} días.",
        ]);
    }

    private function datosFormulario(?Presupuesto $p): array
    {
        $precios = app(PrecioService::class);
        $promos = $precios->promocionesVigentes();

        // Se envían ambos precios (minorista y mayorista) para mostrar el importe en pantalla;
        // el que vale lo vuelve a calcular el servidor al guardar. El costo no se envía.
        $productos = Producto::where('pro_activo', true)->orderBy('pro_nombre')->get()
            ->map(fn ($x) => [
                'id' => $x->pro_id, 'codigo' => $x->pro_codigo, 'nombre' => $x->pro_nombre,
                'stock' => (float) $x->pro_stockactual,
                'precio' => $precios->precioFinal($x, false, $promos),
                'precio_may' => $precios->precioFinal($x, true, $promos),
            ])->values();

        $clientes = Cliente::orderBy('cli_nombre')->get(['cli_id', 'cli_nombre', 'cli_apellido', 'cli_ruc_ci', 'cli_es_mayorista'])
            ->map(fn ($c) => [
                'id' => $c->cli_id, 'nombre' => trim($c->cli_nombre.' '.($c->cli_apellido ?? '')),
                'ruc' => $c->cli_ruc_ci, 'mayorista' => (bool) $c->cli_es_mayorista,
            ])->values();

        return [
            'p' => $p,
            'productos' => $productos,
            'clientes' => $clientes,
            'validezDefecto' => (int) config('presupuestos.validez_defecto_dias', 7),
            'validezMax' => (int) config('presupuestos.validez_maxima_dias', 90),
        ];
    }

    private function errorInesperado(string $accion, \Throwable $e): string
    {
        $codigo = strtoupper(Str::random(6));
        Log::error("[$codigo] Error al $accion: ".$e->getMessage(), ['exception' => $e]);

        return "No se pudo $accion. Código de error: $codigo";
    }
}
