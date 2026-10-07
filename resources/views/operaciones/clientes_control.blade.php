@extends('layouts.admin')

@section('contenido')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            🛡️ Control Operacional de Clientes
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Supervisa límites de crédito, bloqueos y categorías de clientes.</p>
    </div>
</div>

<!-- Tarjetas de Resumen (KPIs) -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-3 mb-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-default dark:border-gray-800 dark:bg-[#1c2434]">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Cliente Top (Mayor Venta)</span>
                <h4 class="text-xl font-bold text-gray-800 dark:text-white mt-1">
                    {{ isset($mejorCliente) ? $mejorCliente->cli_nombre . ' ' .$mejorCliente->cli_apellido : 'Sin ventas' }}
                </h4>
            </div>
            <span class="text-3xl">🏆</span>
        </div>
    </div>
    
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-default dark:border-gray-800 dark:bg-[#1c2434]">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Clientes</span>
                <h4 class="text-xl font-bold text-gray-800 dark:text-white mt-1">{{ $totalClientes ?? 0 }}</h4>
            </div>
            <span class="text-3xl">👥</span>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-default dark:border-gray-800 dark:bg-[#1c2434]">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Clientes Bloqueados</span>
                <h4 class="text-xl font-bold text-red-600 mt-1">{{ $clientesBloqueados ?? 0 }}</h4>
            </div>
            <span class="text-3xl">🚫</span>
        </div>
    </div>
</div>

<!-- Contenedor Principal con Buscador de Clientes -->
<div class="rounded-xl border border-gray-200 bg-white shadow-default dark:border-gray-800 dark:bg-[#1c2434]" x-data="{ search: '' }">
    


  <!-- Barra de Búsqueda Global y Tabla Operacional -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-2xl shadow-default overflow-hidden">
        
        <!-- Buscador de Servidor -->
        <form action="{{ route('operaciones.clientes_control') }}" method="GET" class="p-4 border-b border-gray-200 dark:border-gray-800 flex gap-2">
            <div class="relative w-full max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">🔍</span>
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar cliente por RUC, nombre o apellido..." class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 py-2.5 pl-10 pr-4 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500 transition">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl transition shadow-sm">Buscar</button>
            @if(request('buscar'))
                <a href="{{ route('operaciones.clientes_control') }}" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold text-sm rounded-xl transition" title="Limpiar Filtro">✖</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">Cliente / RUC</th>
                        <th class="py-4 px-6">Volumen Compras</th>
                        <th class="py-4 px-6">Crédito Consumido</th>
                        <th class="py-4 px-6 text-center">⭐ Mayorista</th>
                        <th class="py-4 px-6 text-center">💳 Permitir Crédito</th>
                        <th class="py-4 px-6 text-center">⛔ Bloqueo Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6">
                                <span class="font-bold text-gray-900 dark:text-white block">{{ $cliente->cli_nombre }} {{ $cliente->cli_apellido }}</span>
                                <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">RUC/CI: {{ $cliente->cli_ruc_ci }}</span>
                            </td>
                            <td class="py-4 px-6 font-bold text-gray-800 dark:text-white">
                                Gs. {{ number_format($cliente->ventas_sum_vta_total ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-bold text-orange-600">Gs. {{ number_format($cliente->cuentas_cobrar_sum_cred_saldo_pendiente ?? 0, 0, ',', '.') }}</span>
                                <span class="block text-xs text-gray-400">Límite: Gs. {{ number_format($cliente->cli_limite_credito ?? 0, 0, ',', '.') }}</span>
                            </td>
                            
                            <!-- Botón Mayorista -->
                            <td class="py-4 px-6 text-center">
                                <button onclick="togglePermiso({{ $cliente->cli_id }}, 'cli_es_mayorista')" class="px-3 py-1 rounded-full text-xs font-bold transition {{ $cliente->cli_es_mayorista ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                    {{ $cliente->cli_es_mayorista ? 'SÍ' : 'NO' }}
                                </button>
                            </td>

                            <!-- Botón Permitir Crédito -->
                            <td class="py-4 px-6 text-center">
                                <button onclick="togglePermiso({{ $cliente->cli_id }}, 'cli_permitir_credito')" class="px-3 py-1 rounded-full text-xs font-bold transition {{ $cliente->cli_permitir_credito ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                                    {{ $cliente->cli_permitir_credito ? 'PERMITIDO' : 'PROHIBIDO' }}
                                </button>
                            </td>

                            <!-- Botón Bloqueo Total -->
                            <td class="py-4 px-6 text-center">
                                <button onclick="togglePermiso({{ $cliente->cli_id }}, 'cli_bloqueado')" class="px-3 py-1 rounded-full text-xs font-bold transition {{ $cliente->cli_bloqueado ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                    {{ $cliente->cli_bloqueado ? 'BLOQUEADO' : 'ACTIVO' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-gray-500 font-medium">No se encontraron clientes registrados con ese criterio.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- ================= CONTROLES DE PAGINACIÓN ================= -->
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30">
            {{ $clientes->links() }}
        </div>
        <!-- =========================================================== -->
    </div>
</div>

<!-- Script para manejar los cambios instantáneos al hacer clic -->
<script>
function togglePermiso(clienteId, campo) {
    fetch(`/operaciones/clientes-control/toggle/${clienteId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ campo: campo })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload(); // Recarga para actualizar estados y colores instantáneamente
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
@endsection