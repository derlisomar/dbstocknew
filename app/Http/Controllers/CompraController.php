<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\CompraService;
use App\Services\PagoProveedorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'prov_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:REGISTRADA,ANULADA'],
            'tipo' => ['nullable', 'in:CONTADO,CREDITO'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $consulta = Compra::with(['proveedor', 'cuenta'])->orderByDesc('com_fecha')->orderByDesc('com_id');

        if (! empty($filtros['prov_id'])) {
            $consulta->where('prov_id', $filtros['prov_id']);
        }
        if (! empty($filtros['estado'])) {
            $consulta->where('com_estado', $filtros['estado']);
        }
        if (! empty($filtros['tipo'])) {
            $consulta->where('com_tipo', $filtros['tipo']);
        }
        if (! empty($filtros['desde'])) {
            $consulta->whereDate('com_fecha', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $consulta->whereDate('com_fecha', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['q'])) {
            $consulta->where('com_nro_documento', 'like', '%'.addcslashes($filtros['q'], '%_\\').'%');
        }

        $totalVigente = (float) (clone $consulta)->reorder()->where('com_estado', 'REGISTRADA')->sum('com_total');

        return view('compras.index', [
            'compras' => $consulta->paginate(25)->withQueryString(),
            'proveedores' => Proveedor::orderBy('prov_razonsocial')->get(['prov_id', 'prov_razonsocial']),
            'totalVigente' => $totalVigente,
        ]);
    }

    public function create()
    {
        $productos = Producto::where('pro_activo', true)->orderBy('pro_nombre')
            ->get(['pro_id', 'pro_codigo', 'pro_nombre', 'pro_preciocosto', 'pro_stockactual'])
            ->map(fn ($p) => [
                'id' => $p->pro_id, 'codigo' => $p->pro_codigo, 'nombre' => $p->pro_nombre,
                'costo' => (float) $p->pro_preciocosto, 'stock' => (float) $p->pro_stockactual,
            ])->values();

        return view('compras.create', [
            'proveedores' => Proveedor::orderBy('prov_razonsocial')->get(['prov_id', 'prov_razonsocial']),
            'productos' => $productos,
            'plazo' => (int) config('compras.plazo_defecto_dias', 30),
            'formas' => PagoProveedorService::FORMAS,
        ]);
    }

    public function store(Request $request, CompraService $compras)
    {
        $datos = $request->validate([
            'prov_id' => ['required', 'integer', 'exists:proveedores,prov_id'],
            'fecha' => ['nullable', 'date'],
            'nro_documento' => ['nullable', 'string', 'max:40'],
            'tipo' => ['required', 'in:CONTADO,CREDITO'],
            'vencimiento' => ['nullable', 'date'],
            'forma_pago' => ['nullable', 'in:'.implode(',', PagoProveedorService::FORMAS)],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.pro_id' => ['required', 'integer', 'exists:productos,pro_id'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'items.*.costo' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'items.required' => 'Agregá al menos un producto a la compra.',
            'items.min' => 'Agregá al menos un producto a la compra.',
        ]);

        try {
            $resultado = $compras->registrar($datos, $request->user());
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error al registrar compra: ".$e->getMessage(), ['exception' => $e]);

            return back()->withInput()->with('error', "No se pudo registrar la compra. Código de error: $codigo");
        }

        return redirect()->route('compras.show', $resultado['compra']->com_id)
            ->with('success', 'Compra registrada: el stock y el costo ya están actualizados.')
            ->with('warning', $resultado['avisos'] ? implode(' ', $resultado['avisos']) : null);
    }

    public function show($id)
    {
        $compra = Compra::with(['proveedor', 'usuario', 'detalles.producto', 'cuenta.pagos.usuario'])->findOrFail($id);

        return view('compras.show', compact('compra'));
    }

    public function anular(Request $request, $id, CompraService $compras)
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:200']], ['motivo.required' => 'Indicá el motivo de la anulación.']);

        try {
            $compras->anular((int) $id, $request->user(), $datos['motivo']);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error al anular compra: ".$e->getMessage(), ['exception' => $e]);

            return back()->with('error', "No se pudo anular la compra. Código de error: $codigo");
        }

        return back()->with('success', 'Compra anulada: el stock volvió a su estado anterior y los pagos fueron revertidos.');
    }

    public function devolver(Request $request, $id, CompraService $compras)
    {
        $datos = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $r = $compras->devolver((int) $id, $datos['items'], $request->user(), $datos['motivo'] ?? null);
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error en devolución a proveedor: ".$e->getMessage(), ['exception' => $e]);

            return back()->with('error', "No se pudo registrar la devolución. Código de error: $codigo");
        }

        $msg = 'Devolución registrada por Gs. '.number_format($r['valor'], 0, ',', '.').': el stock bajó y la deuda con el proveedor se redujo en Gs. '.number_format($r['descuento_deuda'], 0, ',', '.').'.';
        $redir = back()->with('success', $msg);
        if ($r['a_reclamar'] > 0) {
            $redir->with('warning', 'Ya se había pagado ese monto: el proveedor te debe Gs. '.number_format($r['a_reclamar'], 0, ',', '.').' (reembolso o nota de crédito), que se gestiona fuera del sistema.');
        }

        return $redir;
    }
}
