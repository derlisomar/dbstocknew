@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    tipoOperacion: 'INGRESO',
    
    abrirModal(tipo) {
        this.tipoOperacion = tipo;
        this.openModal = true;
    }
}" class="space-y-6">

    <!-- Cabecera -->
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <span>⚖️</span> Control de Ingresos y Egresos
        </h2>
        <p class="text-sm text-gray-500 mt-1">Registra y filtra movimientos adicionales de caja afectando saldos en tiempo real.</p>
    </div>

    <!-- TARJETAS KPIs SEMANALES -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- KPI Ingresos -->
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-emerald-100 dark:border-emerald-900 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-500">Ingresos Extras (Esta Semana)</p>
                <h3 class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">Gs. {{ number_format($ingresosSemana, 0, ',', '.') }}</h3>
            </div>
            <div class="p-4 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 rounded-xl text-2xl font-bold">📈</div>
        </div>

        <!-- KPI Egresos -->
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-red-100 dark:border-red-900 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-red-500">Egresos Extras (Esta Semana)</p>
                <h3 class="text-3xl font-black text-red-600 dark:text-red-400 mt-1">Gs. {{ number_format($egresosSemana, 0, ',', '.') }}</h3>
            </div>
            <div class="p-4 bg-red-50 dark:bg-red-900/30 text-red-500 rounded-xl text-2xl font-bold">📉</div>
        </div>
    </div>

    <!-- FILTROS AVANZADOS -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
        <form method="GET" action="{{ route('finanzas.ingresos_egresos') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fecha</label>
                <input type="date" name="fecha" value="{{ request('fecha') }}" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Sucursal</label>
                <select name="suc_id" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none">
                    <option value="">Todas</option>
                    @foreach($sucursales as $suc)
                        <option value="{{ $suc->suc_id }}" {{ request('suc_id') == $suc->suc_id ? 'selected' : '' }}>{{ $suc->suc_nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Caja</label>
                <select name="caj_id" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none">
                    <option value="">Todas las cajas</option>
                    @foreach($cajasActivas as $sesion)
                        <option value="{{ $sesion->caja->caj_id }}" {{ request('caj_id') == $sesion->caja->caj_id ? 'selected' : '' }}>{{ $sesion->caja->caj_nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition text-sm">🔍 Filtrar</button>
                <a href="{{ route('finanzas.ingresos_egresos') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2.5 px-4 rounded-lg font-bold flex items-center justify-center">🔄</a>
            </div>
        </form>
    </div>

    <!-- PANEL DIVIDIDO: INGRESOS (IZQUIERDA) Y EGRESOS (DERECHA) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- COLUMNA IZQUIERDA: INGRESOS -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl shadow-default overflow-hidden flex flex-col">
            <div class="bg-emerald-50 dark:bg-emerald-900/10 p-4 border-b border-emerald-100 flex justify-between items-center">
                <h3 class="font-bold text-emerald-700 dark:text-emerald-400">📥 Historial de Ingresos</h3>
                <button @click="abrirModal('INGRESO')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-bold">+ Nuevo Ingreso</button>
            </div>
            <div class="p-0 overflow-y-auto max-h-[500px]">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($ingresos as $ing)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="p-4">
                                        <p class="font-semibold text-gray-800 dark:text-white">{{ $ing->ie_concepto }}</p>
                                        <p class="text-[11px] text-gray-400">🧮 {{ $ing->sesion->caja->caj_nombre ?? 'Caja Indefinida' }} | 🏢 {{ $ing->sesion->caja->sucursal->suc_nombre ?? 'Sin Sucursal' }}</p>
                                    </td>
                                    <td class="p-4 text-right font-black text-emerald-600">
                                        + Gs. {{ number_format($ing->ie_monto, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="2" class="p-6 text-center text-gray-400 text-xs">No hay ingresos registrados con los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COLUMNA DERECHA: EGRESOS -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl shadow-default overflow-hidden flex flex-col">
            <div class="bg-red-50 dark:bg-red-900/10 p-4 border-b border-red-100 flex justify-between items-center">
                <h3 class="font-bold text-red-700 dark:text-red-400">📤 Historial de Egresos</h3>
                <button @click="abrirModal('EGRESO')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-xs font-bold">- Nuevo Egreso</button>
            </div>
            <div class="p-0 overflow-y-auto max-h-[500px]">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($egresos as $egr)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="p-4">
                                        <p class="font-semibold text-gray-800 dark:text-white">{{ $egr->ie_concepto }}</p>
                                        <p class="text-[11px] text-gray-400">🧮 {{ $egr->sesion->caja->caj_nombre ?? 'Caja Indefinida' }} | 🏢 {{ $egr->sesion->caja->sucursal->suc_nombre ?? 'Sin Sucursal' }}</p>
                                    </td>
                                    <td class="p-4 text-right font-black text-red-500">
                                        - Gs. {{ number_format($egr->ie_monto, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                            <tr><td colspan="2" class="p-6 text-center text-gray-400 text-xs">No hay egresos registrados con los filtros actuales.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL DE NUEVO MOVIMIENTO -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
     <div @click.away="openModal = false" class="bg-white dark:bg-[#1c2434] w-full max-w-md rounded-xl shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 flex justify-between">
                <h3 class="font-bold" :class="tipoOperacion == 'INGRESO' ? 'text-emerald-600' : 'text-red-600'" x-text="tipoOperacion == 'INGRESO' ? '➕ Registrar Ingreso de Caja' : '➖ Registrar Egreso de Caja'"></h3>
                <button @click="openModal = false" class="text-gray-400 font-bold">&times;</button>
            </div>
            
            <form action="{{ route('finanzas.ingresos_egresos.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="tipo" x-model="tipoOperacion">
                
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Caja de Destino / Origen *</label>
                    <select name="ses_id" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none" required>
                        <option value="">-- Selecciona una caja abierta --</option>
                        @foreach($cajasActivas as $sesion)
                            <option value="{{ $sesion->ses_id }}">{{ $sesion->caja->caj_nombre }} ({{ $sesion->caja->sucursal->suc_nombre ?? 'Sucursal' }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Monto (Gs.) *</label>
                    <input type="number" step="0.01" name="monto" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none" required>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Concepto / Motivo *</label>
                    <input type="text" name="concepto" placeholder="Ej. Pago a proveedor, ingreso de sencillo..." class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm outline-none" required>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">Cancelar</button>
                    <button type="submit" class="px-5 py-2 text-white font-bold rounded-lg text-sm transition" :class="tipoOperacion == 'INGRESO' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'">Guardar Operación</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection