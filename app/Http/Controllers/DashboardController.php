<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Definir rango: EL MES ACTUAL
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();
        $mesNombre = $inicioMes->translatedFormat('F Y');

        // 2. KPIs del Mes Actual
        $ventasMes = Venta::whereBetween('vta_fecha', [$inicioMes, $finMes])->where('vta_estado', '!=', 'ANULADA');
        $totalIngresos = $ventasMes->sum('vta_total');
        $totalOperaciones = $ventasMes->count();
        $nuevosClientes = Cliente::count(); // Corregido el problema anterior
        $productosActivos = Producto::where('pro_activo', true)->count();

        // 3. Gráfico de Líneas (Ventas por día del mes ACTUAL)
        $ventasPorDia = Venta::select(DB::raw('DATE(vta_fecha) as fecha'), DB::raw('SUM(vta_total) as total'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])
            ->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
            
        $fechasLine = $ventasPorDia->pluck('fecha')->map(function($date) { return Carbon::parse($date)->format('d/m'); });
        $totalesLine = $ventasPorDia->pluck('total');

        // 4. Gráfico Circular (Métodos de Pago)
        $ventasPorPago = Venta::select('vta_formapago', DB::raw('COUNT(*) as cantidad'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])
            ->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('vta_formapago')
            ->get();
            
        $labelsPie = $ventasPorPago->pluck('vta_formapago');
        $datosPie = $ventasPorPago->pluck('cantidad');

        // 5. Gráfico de Barras (Tipos de Venta)
        $ventasPorTipo = Venta::select('vta_tipo', DB::raw('SUM(vta_total) as total'))
            ->whereBetween('vta_fecha', [$inicioMes, $finMes])
            ->where('vta_estado', '!=', 'ANULADA')
            ->groupBy('vta_tipo')
            ->get();

        $labelsBar = $ventasPorTipo->pluck('vta_tipo');
        $datosBar = $ventasPorTipo->pluck('total');

        return view('dashboard', compact(
            'mesNombre', 'totalIngresos', 'totalOperaciones', 'nuevosClientes', 'productosActivos',
            'fechasLine', 'totalesLine', 'labelsPie', 'datosPie', 'labelsBar', 'datosBar'
        ));
    }
}