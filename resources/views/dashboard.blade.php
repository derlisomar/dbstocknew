@extends('layouts.admin')

@section('contenido')
<!-- Incluir ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="space-y-6" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
    
    <!-- Cabecera -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Panel de Rendimiento</h2>
            <p class="text-sm text-gray-500 mt-1">Resumen estadístico correspondiente al mes de <span class="font-bold text-blue-600 uppercase">{{ $mesNombre }}</span>.</p>
        </div>
        <div class="hidden md:flex gap-2">
            <a href="{{ route('operaciones.ventas.pdf') }}?fecha_inicio={{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}&fecha_fin={{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" target="_blank" class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:text-blue-600 transition flex items-center gap-2">
                📄 Descargar Reporte
            </a>
        </div>
    </div>

    <!-- 1. Tarjetas de KPIs Estéticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <!-- Ingresos -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Ingresos del Mes</p>
                    <h3 class="text-2xl font-black text-gray-800 dark:text-white">Gs. {{ number_format($totalIngresos, 0, ',', '.') }}</h3>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 rounded-xl text-xl">💰</div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs font-medium text-emerald-500">
                <span>↑ 12.5%</span> <span class="text-gray-400">vs mes anterior</span>
            </div>
        </div>

        <!-- Operaciones -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Ventas Exitosas</p>
                    <h3 class="text-2xl font-black text-gray-800 dark:text-white">{{ $totalOperaciones }}</h3>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl text-xl">📈</div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs font-medium text-blue-500">
                <span>Operaciones cerradas</span>
            </div>
        </div>

        <!-- Nuevos Clientes -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Nuevos Clientes</p>
                    <h3 class="text-2xl font-black text-gray-800 dark:text-white">{{ $nuevosClientes }}</h3>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-900/20 text-purple-600 rounded-xl text-xl">👥</div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs font-medium text-purple-500">
                <span>Registrados este mes</span>
            </div>
        </div>

        <!-- Catálogo -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden group">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Productos Activos</p>
                    <h3 class="text-2xl font-black text-gray-800 dark:text-white">{{ $productosActivos }}</h3>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 rounded-xl text-xl">📦</div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs font-medium text-gray-500">
                <span>Disponibles en inventario</span>
            </div>
        </div>
    </div>

    <!-- 2. Gráfico Principal: Líneas (Evolución de Ingresos) -->
    <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Evolución de Ingresos Diarios</h4>
        <div id="chartLine" class="w-full h-80"></div>
    </div>

    <!-- 3. Gráficos Secundarios: Circular y Barras -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Gráfico de Barras -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Ventas por Modalidad</h4>
            <div id="chartBar" class="w-full h-72"></div>
        </div>

        <!-- Gráfico Circular -->
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Distribución por Método de Pago</h4>
            <div id="chartPie" class="w-full h-72 flex justify-center"></div>
        </div>

    </div>
</div>

<!-- ================= SCRIPTS DE APEXCHARTS ================= -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Variables para el modo oscuro automático en los gráficos
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9ca3af' : '#6b7280';
    const gridColor = isDark ? '#374151' : '#f3f4f6';

    // 1. Configuración Gráfico de Líneas (Curva suave)
    var optionsLine = {
        series: [{
            name: 'Ingresos Gs.',
            data: @json($totalesLine->isEmpty() ? [0,0,0] : $totalesLine)
        }],
        chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
        colors: ['#3b82f6'], // Blue-500
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: {
            categories: @json($fechasLine->isEmpty() ? ['Sin datos'] : $fechasLine),
            labels: { style: { colors: textColor } },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: { labels: { style: { colors: textColor }, formatter: (value) => "Gs. " + value.toLocaleString() } },
        grid: { borderColor: gridColor, strokeDashArray: 4, yaxis: { lines: { show: true } } },
        tooltip: { theme: isDark ? 'dark' : 'light' }
    };
    new ApexCharts(document.querySelector("#chartLine"), optionsLine).render();

    // 2. Configuración Gráfico de Barras
    var optionsBar = {
        series: [{
            name: 'Total Vendido',
            data: @json($datosBar->isEmpty() ? [0] : $datosBar)
        }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
        colors: ['#10b981'], // Emerald-500
        plotOptions: {
            bar: { borderRadius: 6, columnWidth: '40%', distributed: true }
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: @json($labelsBar->isEmpty() ? ['Sin datos'] : $labelsBar),
            labels: { style: { colors: textColor } }
        },
        yaxis: { labels: { style: { colors: textColor }, formatter: (val) => val.toLocaleString() } },
        grid: { borderColor: gridColor, strokeDashArray: 4 },
        legend: { show: false },
        tooltip: { theme: isDark ? 'dark' : 'light' }
    };
    new ApexCharts(document.querySelector("#chartBar"), optionsBar).render();

    // 3. Configuración Gráfico Circular (Doughnut)
    var optionsPie = {
        series: @json($datosPie->isEmpty() ? [1] : $datosPie),
        chart: { type: 'donut', height: 300, fontFamily: 'Outfit, sans-serif' },
        labels: @json($labelsPie->isEmpty() ? ['Sin datos'] : $labelsPie),
        colors: ['#8b5cf6', '#f59e0b', '#3b82f6', '#ef4444', '#10b981'], // Colores estéticos variados
        plotOptions: {
            pie: { donut: { size: '65%' } }
        },
        dataLabels: { enabled: false },
        stroke: { show: false },
        legend: { position: 'bottom', labels: { colors: textColor } },
        tooltip: { theme: isDark ? 'dark' : 'light' }
    };
    new ApexCharts(document.querySelector("#chartPie"), optionsPie).render();
});
</script>
@endsection