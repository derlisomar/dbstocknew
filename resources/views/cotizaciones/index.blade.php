@extends('layouts.admin')

@section('contenido')
<div x-data="{ openModal: false }" class="max-w-5xl mx-auto space-y-6">
    
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">💱 Cotizaciones de Moneda</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra el tipo de cambio utilizado en el Punto de Venta.</p>
        </div>
        <button @click="openModal = true" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg transition shadow-md">
            + Actualizar Cotización
        </button>
    </div>

    <!-- Panel de Cotización Actual -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-xl border border-green-200 dark:border-green-900/50 shadow-sm flex items-center gap-6 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10 text-9xl">💵</div>
            <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-3xl">💵</div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dólar Estadounidense (USD)</p>
                <h3 class="text-3xl font-black text-gray-800 dark:text-white mt-1">Gs. {{ number_format($cotizacionActiva->cot_dolar ?? 0, 0, ',', '.') }}</h3>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1c2434] p-6 rounded-xl border border-blue-200 dark:border-blue-900/50 shadow-sm flex items-center gap-6 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10 text-9xl">🇧🇷</div>
            <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-3xl">🇧🇷</div>
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Real Brasileño (BRL)</p>
                <h3 class="text-3xl font-black text-gray-800 dark:text-white mt-1">Gs. {{ number_format($cotizacionActiva->cot_real ?? 0, 0, ',', '.') }}</h3>
            </div>
        </div>
    </div>

    <!-- Historial -->
    <div class="bg-white dark:bg-[#1c2434] rounded-lg shadow-default border border-gray-200 dark:border-gray-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
            <h3 class="font-bold text-gray-800 dark:text-white">📜 Historial de Cambios</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase font-semibold">
                    <tr>
                        <th class="px-6 py-3">Fecha de Registro</th>
                        <th class="px-6 py-3">Cotización Dólar (USD)</th>
                        <th class="px-6 py-3">Cotización Real (BRL)</th>
                        <th class="px-6 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach($historial as $cot)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($cot->cot_fecha)->format('d/m/Y') }}</td>
                        <td class="px-6 py-4">Gs. {{ number_format($cot->cot_dolar, 0, ',', '.') }}</td>
                        <td class="px-6 py-4">Gs. {{ number_format($cot->cot_real, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($cot->cot_activa)
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">ACTIVA</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-bold">HISTÓRICO</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Actualización -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
        <div @click.away="openModal = false" class="bg-white dark:bg-gray-800 w-full max-w-md rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Actualizar Valores de Moneda</h3>
            
            <form action="{{ route('cotizaciones.store') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Nuevo Valor Dólar (Gs.) *</label>
                        <input type="number" name="cot_dolar" value="{{ $cotizacionActiva->cot_dolar ?? '' }}" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 outline-none text-gray-800 dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Nuevo Valor Real (Gs.) *</label>
                        <input type="number" name="cot_real" value="{{ $cotizacionActiva->cot_real ?? '' }}" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 outline-none text-gray-800 dark:text-white" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-bold rounded shadow hover:bg-blue-700">Guardar e Implementar</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection