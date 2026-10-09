<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Inventario: historial de movimientos de stock + ajustes manuales (ingreso, ajuste, conteo).
 */
class InventarioController extends Controller
{
    public function index(Request $request, StockService $stock)
    {
        $consulta = StockMovimiento::with(['producto:pro_id,pro_codigo,pro_nombre', 'usuario'])
            ->orderByDesc('smo_id');

        if ($request->filled('producto')) {
            $consulta->where('pro_id', (int) $request->producto);
        }
        if ($request->filled('tipo') && in_array($request->tipo, StockService::TIPOS, true)) {
            $consulta->where('smo_tipo', $request->tipo);
        }
        if ($request->filled('desde')) {
            $consulta->whereDate('smo_fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $consulta->whereDate('smo_fecha', '<=', $request->hasta);
        }
        if ($request->filled('usuario')) {
            $consulta->where('usu_id', (int) $request->usuario);
        }

        return view('inventario.index', [
            'movimientos' => $consulta->paginate(40)->withQueryString(),
            'productos' => Producto::orderBy('pro_nombre')->get(['pro_id', 'pro_codigo', 'pro_nombre', 'pro_stockactual']),
            'usuarios' => User::orderBy('usu_usuario')->get(['usu_id', 'usu_usuario']),
            'tipos' => StockService::TIPOS,
            'tiposManuales' => StockService::TIPOS_MANUALES,
            'descuadres' => $stock->descuadres(),
            'puedeAjustar' => Gate::allows('STOCK_AJUSTAR'),
        ]);
    }

    public function registrar(Request $request, StockService $stock)
    {
        $datos = $request->validate([
            'pro_id' => ['required', 'integer', 'exists:productos,pro_id'],
            'tipo' => ['required', 'in:'.implode(',', StockService::TIPOS_MANUALES)],
            'cantidad' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'motivo' => ['required', 'string', 'max:200'],
        ]);

        try {
            $mov = $stock->registrarManual((int) $datos['pro_id'], $datos['tipo'], (float) $datos['cantidad'], $datos['motivo']);
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("[$codigo] Error al registrar movimiento de stock: ".$e->getMessage(), ['exception' => $e]);

            return back()->withInput()->with('error', "No se pudo registrar el movimiento. Código de error: $codigo");
        }

        return back()->with('success', 'Movimiento registrado. Stock resultante: '.(float) $mov->smo_stock_resultante.'.');
    }
}
