<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Quien puede ver reportes o el historial de ventas ve toda la empresa.
        // El resto (cajeros) ve solo su propio día: antes cualquiera veía la facturación del mes.
        $verTodo = $usuario->can('REPORTES_VER') || $usuario->can('VENTAS_HISTORIAL');

        $ahora = Carbon::now();
        $inicioMes = $ahora->copy()->startOfMonth();
        $finMes = $ahora->copy()->endOfMonth();
        $mesNombre = $inicioMes->translatedFormat('F Y');
        $hoyInicio = $ahora->copy()->startOfDay();
        $hoyFin = $ahora->copy()->endOfDay();

        // ---- Mi día (todos)
        $misVentasHoy = Venta::where('usu_id', $usuario->usu_id)
            ->whereBetween('vta_fecha', [$hoyInicio, $hoyFin])->where('vta_estado', '!=', 'ANULADA');
        $miDia = [
            'cantidad' => (clone $misVentasHoy)->count(),
            'total' => (float) (clone $misVentasHoy)->sum('vta_total'),
        ];

        $datos = [
            'verTodo' => $verTodo,
            'mesNombre' => $mesNombre,
            'miDia' => $miDia,
            'productosActivos' => Producto::where('pro_activo', true)->count(),
            'stockBajo' => Producto::where('pro_activo', true)->whereColumn('pro_stockactual', '<=', 'pro_stockminimo')->count(),
            'totalIngresos' => 0, 'totalOperaciones' => 0, 'clientesRegistrados' => 0, 'variacionMes' => null,
            'hoy' => null,
            'fechasLine' => collect(), 'totalesLine' => collect(),
            'labelsPie' => collect(), 'datosPie' => collect(), 'labelsBar' => collect(), 'datosBar' => collect(),
        ];

        if (! $verTodo) {
            return view('dashboard', $datos);
        }

        // ---- Mes actual
        $ventasMes = Venta::whereBetween('vta_fecha', [$inicioMes, $finMes])->where('vta_estado', '!=', 'ANULADA');
        $totalIngresos = (float) (clone $ventasMes)->sum('vta_total');
        $datos['totalIngresos'] = $totalIngresos;
        $datos['totalOperaciones'] = (clone $ventasMes)->count();
        $datos['clientesRegistrados'] = Cliente::count();

        // ---- Comparación real contra el mes anterior (mismo tramo de días no, mes completo)
        $inicioPrev = $inicioMes->copy()->subMonthNoOverflow()->startOfMonth();
        $finPrev = $inicioPrev->copy()->endOfMonth();
        $totalPrev = (float) Venta::whereBetween('vta_fecha', [$inicioPrev, $finPrev])->where('vta_estado', '!=', 'ANULADA')->sum('vta_total');
        $datos['variacionMes'] = $totalPrev > 0 ? round((($totalIngresos - $totalPrev) / $totalPrev) * 100, 1) : null;

        // ---- Hoy
        $ventasHoy = Venta::whereBetween('vta_fecha', [$hoyInicio, $hoyFin])->where('vta_estado', '!=', 'ANULADA');
        $deuda = DB::table('cuentas_cobrar')->where('cred_estado', 'PENDIENTE');
        $datos['hoy'] = [
            'ventas_cant' => (clone $ventasHoy)->count(),
            'ventas_total' => (float) (clone $ventasHoy)->sum('vta_total'),
            'cobros_total' => (float) DB::table('cobranzas')->whereBetween('cob_fecha', [$hoyInicio, $hoyFin])
                ->where('cob_estado', '!=', 'ANULADA')->sum('cob_monto_total'),
            'cajas_abiertas' => DB::table('caja_sesiones')->where('ses_estado', 'ABIERTA')->count(),
            'deuda_total' => (float) (clone $deuda)->sum('cred_saldo_pendiente'),
            'deuda_vencida' => (float) (clone $deuda)->where('cred_fecha_vencimiento', '<', $hoyInicio)->sum('cred_saldo_pendiente'),
        ];

        // ---- Gráficos del mes
        $ventasPorDia = Venta::select(DB::raw('DATE(vta_fecha) as fecha'), DB::raw('SUM(vta_total) as total'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('fecha')->orderBy('fecha')->get();
        $datos['fechasLine'] = $ventasPorDia->pluck('fecha')->map(fn ($d) => Carbon::parse($d)->format('d/m'));
        $datos['totalesLine'] = $ventasPorDia->pluck('total');

        $ventasPorPago = Venta::select('vta_formapago', DB::raw('COUNT(*) as cantidad'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('vta_formapago')->get();
        $datos['labelsPie'] = $ventasPorPago->pluck('vta_formapago');
        $datos['datosPie'] = $ventasPorPago->pluck('cantidad');

        $ventasPorTipo = Venta::select('vta_tipo', DB::raw('SUM(vta_total) as total'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('vta_tipo')->get();
        $datos['labelsBar'] = $ventasPorTipo->pluck('vta_tipo');
        $datos['datosBar'] = $ventasPorTipo->pluck('total');

        return view('dashboard', $datos);
    }
}
