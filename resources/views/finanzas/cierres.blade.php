@extends('layouts.admin')

@section('contenido')
<div class="space-y-6">

    <!-- Cabecera y Buscador por Fecha -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🔐 Historial de Cierres y Arqueos</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Consulta los turnos cerrados, filtra por fecha y revisa los arqueos multimoneda.</p>
        </div>
        
        <!-- Formulario de Filtro -->
        <form action="{{ route('finanzas.cierres') }}" method="GET" class="flex items-center gap-2 w-full md:w-auto">
            <div class="flex flex-col">
                <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Buscar por Fecha de Cierre</label>
                <input type="date" name="fecha_cierre" value="{{ request('fecha_cierre') }}" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white outline-none">
            </div>
            <div class="flex gap-1 mt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition">Buscar</button>
                @if(request('fecha_cierre'))
                    <a href="{{ route('finanzas.cierres') }}" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg text-sm font-bold transition">✖</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabla Historial Multimoneda -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">ID / Usuario</th>
                        <th class="py-4 px-6">Sucursal / Caja</th>
                        <th class="py-4 px-6">Apertura / Cierre</th>
                        <th class="py-4 px-6 text-right">Monto Inicial</th>
                        <th class="py-4 px-6 text-right">Cierre Real (Arqueo)</th>
                        <th class="py-4 px-6 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($sesiones as $sesion)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6">
                                <span class="font-bold text-gray-800 dark:text-white">#{{ $sesion->ses_id }}</span><br>
                                <span class="text-xs text-gray-500">👤 {{ $sesion->usuario->usu_nombre ?? 'N/A' }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-bold text-blue-600 dark:text-blue-400">{{ $sesion->caja->caj_nombre ?? 'Caja' }}</span><br>
                                <span class="text-xs text-gray-500">{{ $sesion->caja->sucursal->suc_nombre ?? 'Sucursal' }}</span>
                            </td>
                            <td class="py-4 px-6 text-xs">
                                <div class="text-emerald-600 dark:text-emerald-400 font-medium">Abrió: {{ $sesion->ses_fecha_apertura }}</div>
                                <div class="text-red-500 font-medium mt-1">Cerró: {{ $sesion->ses_fecha_cierre }}</div>
                            </td>
                            <td class="py-4 px-6 text-right text-xs space-y-0.5">
                                <div class="font-bold text-gray-800 dark:text-gray-200">Gs. {{ number_format($sesion->ses_monto_inicial_gs ?? 0, 0, ',', '.') }}</div>
                                <div class="text-gray-500">$ {{ number_format($sesion->ses_monto_inicial_usd ?? 0, 2, '.', ',') }}</div>
                                <div class="text-gray-500">R$ {{ number_format($sesion->ses_monto_inicial_brl ?? 0, 2, '.', ',') }}</div>
                            </td>
                            <td class="py-4 px-6 text-right text-xs space-y-0.5">
                                <div class="font-bold text-emerald-600 dark:text-emerald-400">Gs. {{ number_format($sesion->ses_monto_cierre_gs ?? 0, 0, ',', '.') }}</div>
                                <div class="text-gray-500">$ {{ number_format($sesion->ses_monto_cierre_usd ?? 0, 2, '.', ',') }}</div>
                                <div class="text-gray-500">R$ {{ number_format($sesion->ses_monto_cierre_brl ?? 0, 2, '.', ',') }}</div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <span class="px-3 py-1 text-[11px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 rounded-lg">Cerrada</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">No se encontraron cierres de caja en la fecha seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30">
            {{ $sesiones->links() }}
        </div>
    </div>

</div>
@endsection