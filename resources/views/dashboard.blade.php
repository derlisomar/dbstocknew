@extends('layouts.admin')

@section('contenido')
<!-- Incluir ApexCharts -->
@if($verTodo)
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endif

<div class="space-y-6" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
    
    <!-- Cabecera -->
    <div class="flex justify-between items-end">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Panel de Rendimiento</h2>
            <p class="text-sm text-gray-500 mt-1">Resumen correspondiente al mes de <span class="font-bold text-blue-600 uppercase">{{ $mesNombre }}</span>.</p>
        </div>
        @can('VENTAS_HISTORIAL')
        <div class="hidden md:flex gap-2">
            <a href="{{ route('operaciones.ventas.pdf') }}?fecha_inicio={{ \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') }}&fecha_fin={{ \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d') }}" target="_blank" class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:text-blue-600 transition flex items-center gap-2">
                📄 Descargar Reporte
            </a>
        </div>
        @endcan
    </div>

<style>
.dk-sec{--c:#3b82f6;--bg:rgba(59,130,246,.14);background:#fff;border:1px solid #e5e7eb;border-left:4px solid var(--c);border-radius:12px;padding:14px 16px;margin-bottom:16px}
.dk-sec.amb{--c:#f59e0b;--bg:rgba(245,158,11,.16)}
.dk-sec.vio{--c:#8b5cf6;--bg:rgba(139,92,246,.16)}
html.dark .dk-sec{background:#1c2434;border-color:#2e3a47;border-left-color:var(--c)}
.dk-head{display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.dk-badge{font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;padding:2px 8px;border-radius:6px;background:var(--bg);color:var(--c)}
.dk-sub{font-size:12px;color:#9ca3af}
.dk-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
.dk-tile{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;background:#f9fafb;border:1px solid #eef0f3;min-width:0}
html.dark .dk-tile{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.07)}
.dk-ico{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:var(--bg)}
.dk-txt{min-width:0}
.dk-lbl{font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
html.dark .dk-lbl{color:#9ca3af}
.dk-val{font-size:17px;font-weight:800;line-height:1.25;color:#111827;white-space:nowrap}
html.dark .dk-val{color:#fff}
.dk-note{font-size:11px;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dk-red{color:#ef4444}.dk-up{color:#10b981}
.dk-charts{display:grid;gap:12px;margin-top:14px}
.dk-two{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
.dk-chart{border:1px solid #e5e7eb;border-radius:10px;padding:12px}
html.dark .dk-chart{border-color:#2e3a47}
.dk-chart h4{font-size:13px;font-weight:700;margin:0 0 6px;color:#1f2937}
html.dark .dk-chart h4{color:#fff}
</style>
    @if(! $verTodo)
    <section class="dk-sec ">
        <div class="dk-head"><span class="dk-badge">Mi día</span><span class="dk-sub">{{ \Carbon\Carbon::now()->locale('es')->translatedFormat('l d \\d\\e F') }}</span></div>
        <div class="dk-grid">
            <div class="dk-tile"><div class="dk-ico">🧾</div><div class="dk-txt"><div class="dk-lbl">Mis ventas</div><div class="dk-val">{{ $miDia['cantidad'] }}</div><div class="dk-note ">Hechas hoy</div></div></div>
            <div class="dk-tile"><div class="dk-ico">💵</div><div class="dk-txt"><div class="dk-lbl">Mi total</div><div class="dk-val">Gs. {{ number_format($miDia['total'], 0, ',', '.') }}</div><div class="dk-note ">Sin anuladas</div></div></div>
            <div class="dk-tile"><div class="dk-ico">⚠️</div><div class="dk-txt"><div class="dk-lbl">Stock bajo mínimo</div><div class="dk-val">{{ $stockBajo }}</div><div class="dk-note ">Productos por reponer</div></div></div>
        </div>
    </section>
    @else
    <section class="dk-sec ">
        <div class="dk-head"><span class="dk-badge">Hoy</span><span class="dk-sub">{{ \Carbon\Carbon::now()->locale('es')->translatedFormat('l d \\d\\e F') }}</span></div>
        <div class="dk-grid">
            <div class="dk-tile"><div class="dk-ico">📆</div><div class="dk-txt"><div class="dk-lbl">Ventas</div><div class="dk-val">Gs. {{ number_format($hoy['ventas_total'], 0, ',', '.') }}</div><div class="dk-note ">{{ $hoy['ventas_cant'] }} venta(s)</div></div></div>
            <div class="dk-tile"><div class="dk-ico">🤝</div><div class="dk-txt"><div class="dk-lbl">Cobros</div><div class="dk-val">Gs. {{ number_format($hoy['cobros_total'], 0, ',', '.') }}</div><div class="dk-note ">Créditos cobrados</div></div></div>
            <div class="dk-tile"><div class="dk-ico">🏧</div><div class="dk-txt"><div class="dk-lbl">Cajas abiertas</div><div class="dk-val">{{ $hoy['cajas_abiertas'] }}</div><div class="dk-note ">Sin cerrar</div></div></div>
        </div>
    </section>
    <section class="dk-sec amb">
        <div class="dk-head"><span class="dk-badge">Situación actual</span><span class="dk-sub">Al día de hoy, no depende del mes</span></div>
        <div class="dk-grid">
            <div class="dk-tile"><div class="dk-ico">📒</div><div class="dk-txt"><div class="dk-lbl">Deuda de clientes</div><div class="dk-val">Gs. {{ number_format($hoy['deuda_total'], 0, ',', '.') }}</div><div class="dk-note ">Saldo pendiente</div></div></div>
            <div class="dk-tile"><div class="dk-ico">⏰</div><div class="dk-txt"><div class="dk-lbl">Deuda vencida</div><div class="dk-val">Gs. {{ number_format($hoy['deuda_vencida'], 0, ',', '.') }}</div><div class="dk-note dk-red">Pasó el vencimiento</div></div></div>
            <div class="dk-tile"><div class="dk-ico">⚠️</div><div class="dk-txt"><div class="dk-lbl">Stock bajo mínimo</div><div class="dk-val">{{ $stockBajo }}</div><div class="dk-note ">Productos por reponer</div></div></div>
        </div>
    </section>
    <section class="dk-sec vio">
        <div class="dk-head"><span class="dk-badge">Este mes</span><span class="dk-sub">{{ \Carbon\Carbon::now()->locale('es')->translatedFormat('F Y') }} · acumulado</span></div>
        <div class="dk-grid">
            <div class="dk-tile"><div class="dk-ico">💰</div><div class="dk-txt"><div class="dk-lbl">Ingresos</div><div class="dk-val">Gs. {{ number_format($totalIngresos, 0, ',', '.') }}</div>
                <div class="dk-note">@if($variacionMes === null)Sin mes anterior para comparar @else<span class="{{ $variacionMes >= 0 ? 'dk-up' : 'dk-red' }}">{{ $variacionMes >= 0 ? '↑' : '↓' }} {{ abs($variacionMes) }}%</span> vs mes anterior @endif</div></div></div>
            <div class="dk-tile"><div class="dk-ico">📈</div><div class="dk-txt"><div class="dk-lbl">Ventas</div><div class="dk-val">{{ $totalOperaciones }}</div><div class="dk-note ">Operaciones del mes</div></div></div>
            <div class="dk-tile"><div class="dk-ico">👥</div><div class="dk-txt"><div class="dk-lbl">Clientes</div><div class="dk-val">{{ $clientesRegistrados }}</div><div class="dk-note ">Registrados en total</div></div></div>
            <div class="dk-tile"><div class="dk-ico">📦</div><div class="dk-txt"><div class="dk-lbl">Productos activos</div><div class="dk-val">{{ $productosActivos }}</div><div class="dk-note ">En el catálogo</div></div></div>
        </div>
        <div class="dk-charts">
            <div class="dk-chart"><h4>Evolución de ingresos diarios</h4><div id="chartLine" style="width:100%;height:256px"></div></div>
            <div class="dk-two">
                <div class="dk-chart"><h4>Ventas por modalidad</h4><div id="chartBar" style="width:100%;height:240px"></div></div>
                <div class="dk-chart"><h4>Distribución por método de pago</h4><div id="chartPie" style="width:100%;height:240px;display:flex;justify-content:center"></div></div>
            </div>
        </div>
    </section>
    @endif
</div>

@if($verTodo)
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
@endif
@endsection