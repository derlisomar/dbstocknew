@extends('layouts.admin')

@section('contenido')
<div x-data="{ openTransferenciaModal: false }" class="space-y-6">

   <!-- Cabecera con Botón de Transferencia -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span>💸</span> Movimientos y Transferencias entre Cajas
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Consulta el historial detallado, filtra por fechas y busca por operación o concepto.</p>
        </div>

        <!-- NUEVO BOTÓN EN LA ZONA ROJA MARCADA -->
        <button @click="openTransferenciaModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-xl transition shadow-md flex items-center gap-2 text-sm flex-shrink-0">
            <span>🔄</span> Realizar Transferencia
        </button>
    </div>

    <!-- Mostrar Error si no hay saldo suficiente -->
    @if($errors->any())
        <div class="p-4 bg-red-100 dark:bg-red-500/10 border border-red-400 text-red-700 dark:text-red-400 rounded-xl font-bold text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    @if(session('success'))
        <div class="p-4 bg-emerald-100 border border-emerald-400 text-emerald-700 rounded-xl font-bold text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- PANEL DE FILTROS Y BUSCADOR -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
        <form method="GET" action="{{ route('finanzas.movimientos') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            
            <!-- Buscador por Operación / Concepto -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Buscar Operación</label>
                <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Ej. Venta, Cobro, Transferencia..." class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
            </div>

            <!-- Fecha Desde -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fecha Desde</label>
                <input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
            </div>

            <!-- Fecha Hasta -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fecha Hasta</label>
                <input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
            </div>

            <!-- Botones de Acción -->
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-xl transition text-sm shadow-sm">🔍 Filtrar Resultados</button>
                <a href="{{ route('finanzas.movimientos') }}" class="px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold rounded-xl text-sm flex items-center justify-center transition" title="Limpiar filtros">🔄</a>
            </div>
        </form>
    </div>

    <!-- TABLAS SEPARADAS: INGRESOS Y EGRESOS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- COLUMNA DE INGRESOS -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 border-b border-emerald-100 dark:border-emerald-900/50 flex justify-between items-center">
                <h3 class="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-2"><span>📥</span> Ingresos Registrados</h3>
                <span class="text-xs font-bold px-2.5 py-1 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 rounded-lg">Entradas de Dinero</span>
            </div>
            
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php $ingresosList = $movimientos->where('mov_tipo', 'INGRESO'); @endphp
                        @forelse($ingresosList as $mov)
                            <tr class="hover:bg-emerald-50/30 dark:hover:bg-emerald-900/10 transition">
                                <td class="p-4">
                                    <span class="text-[11px] font-semibold text-gray-400 block">{{ $mov->mov_fecha ?? $mov->created_at }}</span>
                                    <p class="font-bold text-gray-800 dark:text-white mt-0.5">{{ $mov->mov_concepto }}</p>
                                    <span class="text-[11px] text-blue-600 dark:text-blue-400 font-medium">🧮 Caja: {{ $mov->sesion->caja->caj_nombre ?? 'N/A' }}</span>
                                </td>
                                <td class="p-4 text-right font-black text-emerald-600">
                                    + Gs. {{ number_format($mov->mov_monto, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="p-8 text-center text-gray-400 text-xs">No hay ingresos registrados en esta página o con los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COLUMNA DE EGRESOS -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div class="bg-red-50 dark:bg-red-900/20 p-4 border-b border-red-100 dark:border-red-900/50 flex justify-between items-center">
                <h3 class="font-bold text-red-700 dark:text-red-400 flex items-center gap-2"><span>📤</span> Egresos Registrados</h3>
                <span class="text-xs font-bold px-2.5 py-1 bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 rounded-lg">Salidas de Dinero</span>
            </div>
            
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php $egresosList = $movimientos->where('mov_tipo', 'EGRESO'); @endphp
                        @forelse($egresosList as $mov)
                            <tr class="hover:bg-red-50/30 dark:hover:bg-red-900/10 transition">
                                <td class="p-4">
                                    <span class="text-[11px] font-semibold text-gray-400 block">{{ $mov->mov_fecha ?? $mov->created_at }}</span>
                                    <p class="font-bold text-gray-800 dark:text-white mt-0.5">{{ $mov->mov_concepto }}</p>
                                    <span class="text-[11px] text-blue-600 dark:text-blue-400 font-medium">🧮 Caja: {{ $mov->sesion->caja->caj_nombre ?? 'N/A' }}</span>
                                </td>
                                <td class="p-4 text-right font-black text-red-500">
                                    - Gs. {{ number_format($mov->mov_monto, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="p-8 text-center text-gray-400 text-xs">No hay egresos registrados en esta página o con los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

  

    <!-- CONTROLES DE PAGINACIÓN -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
        {{ $movimientos->links() }}
    </div>

    <!-- MODAL DE TRANSFERENCIA ENTRE CAJAS -->
<div x-show="openTransferenciaModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div @click.away="openTransferenciaModal = false" class="bg-white dark:bg-[#1c2434] w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-800">
        <div class="bg-indigo-600 p-5 text-white flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center gap-2"><span>🔄</span> Transferencia de Efectivo</h3>
            <button @click="openTransferenciaModal = false" class="text-white hover:text-gray-200 text-xl font-bold">&times;</button>
        </div>
        
        <form action="{{ route('finanzas.transferir') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Caja Origen (Solo cajas abiertas) *</label>
                <select name="ses_id_origen" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-indigo-500" required>
                    <option value="">Seleccione desde dónde sale el dinero...</option>
                    @foreach($sesionesAbiertas ?? [] as $sesion)
                        <option value="{{ $sesion->ses_id }}">
                            {{ $sesion->caja->caj_nombre }} - Gs. {{ number_format($sesion->caja->caj_saldo_gs, 0, ',', '.') }} | $ {{ $sesion->caja->caj_saldo_usd }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Caja Destino *</label>
                <select name="caj_id_destino" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-indigo-500" required>
                    <option value="">Seleccione a qué caja va el dinero...</option>
                    @foreach($cajas ?? [] as $caj)
                        <option value="{{ $caj->caj_id }}">[{{ $caj->sucursal->suc_nombre ?? 'SUC' }}] - {{ $caj->caj_nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Moneda *</label>
                    <select name="moneda" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-indigo-500" required>
                        <option value="GS">Guaraníes (Gs.)</option>
                        <option value="USD">Dólares ($)</option>
                        <option value="BRL">Reales (R$)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Monto a Transferir *</label>
                    <input type="number" step="0.01" name="monto" class="w-full rounded-xl bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 font-bold" required min="1" placeholder="0">
                </div>
            </div>

            <!-- NUEVO CAMPO DE OBSERVACIÓN -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Observación adicional</label>
                <input type="text" name="observacion" placeholder="Escriba un detalle o motivo..." class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-indigo-500">
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-4">
                <button type="button" @click="openTransferenciaModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold rounded-xl text-sm transition">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition shadow-md">Ejecutar Transferencia</button>
            </div>
        </form>
    </div>
</div>

</div>

 
@endsection