@extends('layouts.admin')
@section('contenido')
<div class="space-y-6">
    <!-- Cabecera y Botones de Exportación -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🎯 Análisis ABC y Rentabilidad</h2>
            <p class="text-sm text-gray-500">Márgenes de utilidad real y clasificación de Pareto (80/20).</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('operaciones.reporte_abc.excel', request()->all()) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">🟢 Descargar Excel (CSV)</a>
            <a href="{{ route('operaciones.reporte_abc.pdf', request()->all()) }}" target="_blank" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">📄 Imprimir / PDF</a>
        </div>
    </div>

    <!-- Filtros Multivariables -->
    <div class="bg-white dark:bg-[#1c2434] p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm">
        <form method="GET" action="{{ route('operaciones.reporte_abc') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase">Sucursal</label>
                <select name="suc_id" class="w-full mt-1 border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white">
                    <option value="">Todas</option>
                    @foreach($sucursales as $suc)
                        <option value="{{ $suc->suc_id }}" {{ request('suc_id') == $suc->suc_id ? 'selected' : '' }}>{{ $suc->suc_nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase">Fechas</label>
                <input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}" class="w-full mt-1 border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase">Hasta</label>
                <input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}" class="w-full mt-1 border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase">Método Pago</label>
                <select name="vta_formapago" class="w-full mt-1 border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white">
                    <option value="">Todos</option>
                    <option value="EFECTIVO" {{ request('vta_formapago') == 'EFECTIVO' ? 'selected' : '' }}>Efectivo</option>
                    <option value="TARJETA" {{ request('vta_formapago') == 'TARJETA' ? 'selected' : '' }}>Tarjeta</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Filtrar Datos</button>
        </form>
    </div>

    <!-- Tabla Dinámica ABC -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 uppercase text-xs">
                <tr>
                    <th class="p-4">Clase</th>
                    <th class="p-4">Producto</th>
                    <th class="p-4 text-center">Unid. Vendidas</th>
                    <th class="p-4 text-right">Ingreso (Gs.)</th>
                    <th class="p-4 text-right">Costo Real</th>
                    <th class="p-4 text-right">Utilidad Bruta</th>
                    <th class="p-4 text-center">Margen (%)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                @foreach($datos as $item)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                    <td class="p-4 text-center font-black">
                        @if($item->clasificacion_abc == 'A') <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-lg">A</span>
                        @elseif($item->clasificacion_abc == 'B') <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-lg">B</span>
                        @else <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-lg">C</span>
                        @endif
                    </td>
                    <td class="p-4 font-bold">{{ $item->pro_nombre }} <br><span class="text-xs font-normal text-gray-400">Stock Actual: {{ $item->pro_stockactual }}</span></td>
                    <td class="p-4 text-center">{{ $item->total_vendido }}</td>
                    <td class="p-4 text-right">Gs. {{ number_format($item->ingresos_totales, 0, ',', '.') }}</td>
                    <td class="p-4 text-right text-red-500">Gs. {{ number_format($item->costo_total, 0, ',', '.') }}</td>
                    <td class="p-4 text-right font-bold text-emerald-600">Gs. {{ number_format($item->utilidad_bruta, 0, ',', '.') }}</td>
                    <td class="p-4 text-center font-bold">
                        @if($item->sin_costo)
                            <span class="text-amber-600 text-xs" title="Este producto no tiene costo cargado">Sin costo</span>
                        @else
                            <span class="{{ $item->margen_porcentual < 20 ? 'text-red-500' : 'text-emerald-500' }}">
                                {{ number_format($item->margen_porcentual, 1) }}%
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection