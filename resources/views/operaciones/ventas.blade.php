@extends('layouts.admin')

@section('contenido')
<div class="space-y-6" x-data="historialVentasApp()">
    <!-- Cabecera -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span>📈</span> Consulta e Historial Avanzado de Ventas
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Filtra las operaciones por cliente o fechas y visualiza métricas detalladas.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="{{ route('operaciones.ventas.excel', request()->all()) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition flex items-center gap-2 shadow-sm">
                <span>🟢</span> Excel
            </a>
            <a href="{{ route('operaciones.ventas.pdf', request()->all()) }}" target="_blank" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition flex items-center gap-2 shadow-sm">
                <span>📄</span> PDF
            </a>
        </div>
    </div>

    <!-- TARJETAS DE ESTADÍSTICAS (KPIs) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-4">
            <div class="p-4 bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-xl text-2xl font-bold">🛒</div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total de Compras (Rango)</p>
                <h3 class="text-2xl font-black text-gray-800 dark:text-white mt-0.5">{{ $totalComprasCount }} <span class="text-sm font-normal text-gray-500">transacciones</span></h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-4">
            <div class="p-4 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-xl text-2xl font-bold">₲</div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Monto Acumulado (Gs.)</p>
                <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">Gs. {{ number_format($totalMontoGs, 0, ',', '.') }}</h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center gap-4">
            <div class="p-4 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-xl text-2xl font-bold">💱</div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Equivalente Estimado</p>
                <div class="text-sm font-bold text-gray-800 dark:text-white mt-0.5">
                    $ {{ number_format($tasaUsd > 0 ? $totalMontoGs / $tasaUsd : 0, 2, '.', ',') }} USD <br>
                    <span class="text-xs text-gray-400">R$ {{ number_format($tasaBrl > 0 ? $totalMontoGs / $tasaBrl : 0, 2, '.', ',') }} BRL</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
 <!-- Panel de Filtros -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm">
        <form action="{{ route('operaciones.ventas') }}" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Filtrar por Cliente</label>
                <select name="cli_id" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none">
                    <option value="">-- Todos los Clientes --</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->cli_id }}" {{ request('cli_id') == $cli->cli_id ? 'selected' : '' }}>
                            {{ $cli->cli_ruc_ci }} - {{ $cli->cli_nombre }} {{ $cli->cli_apellido }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Fecha de Inicio con valor por defecto del mes actual -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="{{ request('fecha_inicio', now()->startOfMonth()->format('Y-m-d')) }}" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white outline-none">
            </div>
            
            <!-- Fecha de Fin con valor por defecto del mes actual -->
            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" value="{{ request('fecha_fin', now()->endOfMonth()->format('Y-m-d')) }}" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white outline-none">
            </div>

            <div>
                <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Tipo / Pago</label>
                <select name="vta_tipo" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white outline-none">
                    <option value="">Tipo Ventas</option>
                    <option value="CONTADO" {{ request('vta_tipo') == 'CONTADO' ? 'selected' : '' }}>Contado</option>
                    <option value="CREDITO" {{ request('vta_tipo') == 'CREDITO' ? 'selected' : '' }}>Crédito</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition shadow-sm text-sm">🔍 Filtrar</button>
                <a href="{{ route('operaciones.ventas') }}" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 text-gray-700 dark:text-gray-200 py-2.5 px-3 rounded-lg text-sm font-bold flex items-center justify-center" title="Restablecer al mes actual">🔄</a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-3.5 px-4">ID / Fecha</th>
                        <th class="py-3.5 px-4">Cliente RUC / Nombre</th>
                        <th class="py-3.5 px-4 text-center">Tipo / Pago</th>
                        <th class="py-3.5 px-4 text-center">Ítems</th>
                        <th class="py-3.5 px-4 text-right">Total (Gs.)</th>
                        <th class="py-3.5 px-4 text-right">Divisas ($ / R$)</th>
                        <th class="py-3.5 px-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($ventas as $venta)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-gray-900 dark:text-white block">#{{ $venta->vta_id }}</span>
                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($venta->vta_fecha)->format('d/m/Y H:i') }}</span>
                                @if($venta->vta_estado === 'ANULADA')
                                    <span class="mt-1 inline-block px-2 py-0.5 bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400 text-[10px] font-bold rounded">ANULADA</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-semibold block text-gray-800 dark:text-white">{{ $venta->cliente->cli_nombre ?? 'Consumidor Final' }} {{ $venta->cliente->cli_apellido ?? '' }}</span>
                                <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">RUC/CI: {{ $venta->cliente->cli_ruc_ci ?? 'S/N' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-center text-xs">
                                <span class="block font-bold {{ $venta->vta_tipo == 'CONTADO' ? 'text-emerald-600' : 'text-orange-500' }}">{{ $venta->vta_tipo }}</span>
                                <span class="text-gray-400">{{ $venta->vta_formapago ?? 'EFECTIVO' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold">
                                {{ $venta->detalles->sum('det_cantidad') }} unid.
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold {{ $venta->vta_estado === 'ANULADA' ? 'text-gray-400 line-through' : 'text-emerald-600 dark:text-emerald-400' }}">
                                Gs. {{ number_format($venta->vta_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right text-xs {{ $venta->vta_estado === 'ANULADA' ? 'text-gray-400 line-through' : '' }}">
                                <div>$ {{ number_format($tasaUsd > 0 ? $venta->vta_total / $tasaUsd : 0, 2, '.', ',') }}</div>
                                <div class="text-gray-400">R$ {{ number_format($tasaBrl > 0 ? $venta->vta_total / $tasaBrl : 0, 2, '.', ',') }}</div>
                            </td>
                            
                            <td class="py-3.5 px-4 text-center space-x-1">
                                @if($venta->vta_estado !== 'ANULADA')
                                    <!-- Botón que activa el modal pasando el JSON de la venta -->
                                <button @click='abrirModalDevolucion(@json($venta))' class="text-amber-600 bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 px-2.5 py-1 rounded font-bold text-xs transition" title="Gestionar Devoluciones">
                                    🔄 Devolución
                                </button>

                                    <form action="{{ route('operaciones.ventas.anular', $venta->vta_id) }}" method="POST" class="form-anular inline-block">
                                        @csrf
                                        <button type="button" class="btn-anular text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:hover:bg-red-900/50 px-2.5 py-1 rounded font-bold text-xs transition" title="Anular Venta Completa">
                                            ❌ Anular
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-xs italic">Sin acción</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-gray-500 font-medium">No se encontraron registros de ventas con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
 
            <!-- ================= CONTROLES DE PAGINACIÓN ================= -->
            <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30">
                {{ $ventas->links() }}
            </div>
            <!-- =========================================================== -->

    <!-- MODAL DE DEVOLUCIÓN DE ÍTEMS -->
    <div x-show="showDevolucionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="showDevolucionModal = false" class="bg-white dark:bg-[#1c2434] w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-800">
        
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
            <div>
                <h3 class="text-base font-black text-gray-800 dark:text-white">🔄 Gestión de Devolución - Venta #<span x-text="ventaSeleccionada?.vta_id"></span></h3>
                <p class="text-xs text-gray-400">Seleccione los ítems y cantidades a devolver o marque devolución total.</p>
            </div>
            <button @click="showDevolucionModal = false" class="text-gray-400 hover:text-gray-600 font-bold text-lg">✕</button>
        </div>

        <form :action="'/operaciones/ventas/' + (ventaSeleccionada?.vta_id || '') + '/devolver'" method="POST" class="p-6 space-y-4">
            @csrf
            
            <!-- Botón de Devolución Total rápida -->
            <div class="flex justify-between items-center bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl border border-blue-100 dark:border-blue-800">
                <span class="text-xs font-bold text-blue-700 dark:text-blue-300">¿Desea devolver la totalidad de los ítems?</span>
                <button type="button" @click="marcarDevolucionTotal()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-black transition">
                    Marcar Totalidad
                </button>
            </div>

            <!-- Tabla de Ítems de la Venta -->
            <div class="max-h-64 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900 uppercase text-gray-400 sticky top-0">
                        <tr>
                            <th class="py-2.5 px-3">Producto</th>
                            <th class="py-2.5 px-3 text-center">Comprado</th>
                            <th class="py-2.5 px-3 text-right">Precio Unit.</th>
                            <th class="py-2.5 px-3 text-center">A Devolver</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                        <template x-for="det in ventaSeleccionada?.detalles" :key="det.det_vta_id">
                            <tr>
                                <td class="py-2.5 px-3 font-bold" x-text="det.producto?.pro_name || det.producto?.pro_nombre || 'Producto #' + det.pro_id"></td>
                                <td class="py-2.5 px-3 text-center font-semibold" x-text="det.det_cantidad + ' unid.'"></td>
                                <td class="py-2.5 px-3 text-right" x-text="'Gs. ' + Number(det.det_preciounitario).toLocaleString('es-PY')"></td>
                                <td class="py-2.5 px-3 text-center">
                                    <input type="number" min="0" :max="det.det_cantidad" x-model.number="devolucionesItems[det.det_vta_id]" :name="'items[' + det.det_vta_id + ']'" class="w-20 text-center py-1 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-xs font-bold text-gray-800 dark:text-white">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>



            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                <button type="button" @click="showDevolucionModal = false" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl text-xs font-bold transition">Cancelar</button>
                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md transition">Confirmar Devolución</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS DE ALPINE.JS Y SWEETALERT2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function historialVentasApp() {
        return {
            showDevolucionModal: false,
            ventaSeleccionada: null,
            devolucionesItems: {},

            abrirModalDevolucion(venta) {
                this.ventaSeleccionada = venta;
                this.devolucionesItems = {};
                if (venta && venta.detalles) {
                    venta.detalles.forEach(d => {
                        this.devolucionesItems[d.det_vta_id] = 0;
                    });
                }
                this.showDevolucionModal = true; // Esto abre el modal al instante
            },

            marcarDevolucionTotal() {
                if (!this.ventaSeleccionada || !this.ventaSeleccionada.detalles) return;
                this.ventaSeleccionada.detalles.forEach(d => {
                    this.devolucionesItems[d.det_vta_id] = d.det_cantidad;
                });
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const botonesAnular = document.querySelectorAll('.btn-anular');
        
        botonesAnular.forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('.form-anular');
                
                Swal.fire({
                    title: '¿Deseas anular esta venta?',
                    text: "El inventario será devuelto al stock y el balance de caja se ajustará automáticamente.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, anular',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        @if(session('success'))
            Swal.fire('¡Éxito!', '{{ session('success') }}', 'success');
        @endif
        @if(session('error'))
            Swal.fire('Error', '{{ session('error') }}', 'error');
        @endif
    });
</script>
@endsection