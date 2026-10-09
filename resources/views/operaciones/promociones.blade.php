@extends('layouts.admin')

@section('contenido')

<div class="container mx-auto" x-data="{ 
    showModal: false, editMode: false, prom_id: '',
    prom_nombre: '', aplicaA: 'PRODUCTO', tipoDesc: 'PORCENTAJE', 
    prom_valor: '', prom_fecha_fin: '', cat_id: '', pro_id: '',
    
    openCreate() {
        this.editMode = false; this.prom_id = ''; this.prom_nombre = '';
        this.aplicaA = 'PRODUCTO'; this.tipoDesc = 'PORCENTAJE';
        this.prom_valor = ''; this.prom_fecha_fin = ''; this.cat_id = ''; this.pro_id = '';
        this.showModal = true;
    },
    
    openEdit(promo) {
        this.editMode = true; this.prom_id = promo.prom_id; 
        this.prom_nombre = promo.prom_nombre; this.aplicaA = promo.prom_aplica_a; 
        this.tipoDesc = promo.prom_tipo_descuento; this.prom_valor = promo.prom_valor; 
        this.prom_fecha_fin = promo.prom_fecha_fin; this.cat_id = promo.cat_id || ''; 
        this.pro_id = promo.pro_id || ''; this.showModal = true;
    }
}">

<div class="container mx-auto" x-data="{ showModal: false, aplicaA: 'PRODUCTO', tipoDesc: 'PORCENTAJE' }">
    
    <!-- Cabecera y KPIs -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">🎁 Control de Promociones</h1>
            <p class="text-sm text-gray-500 mt-1">Gestiona descuentos por límite de tiempo, categoría o producto individual.</p>
        </div>
        
        <div class="flex gap-4">
            <div class="bg-white dark:bg-[#1c2434] px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-4">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Promos Activas</p>
                    <h4 class="text-2xl font-black text-purple-600 leading-none mt-1">{{ $totalPromos }}</h4>
                </div>
                <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-purple-500 text-xl">🏷️</div>
            </div>
            <div class="bg-white dark:bg-[#1c2434] px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-4">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Prod. Afectados</p>
                    <h4 class="text-2xl font-black text-emerald-600 leading-none mt-1">{{ $productosAfectados }}</h4>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-emerald-500 text-xl">📦</div>
            </div>
           <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-bold shadow-md transition-colors flex items-center gap-2">
                + Nueva Promoción
            </button>
        </div>
    </div>

    <!-- Tabla de Promociones -->
    <div class="bg-white dark:bg-[#1c2434] rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-400 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <tr>
                    <th class="py-3 px-5">Promoción</th>
                    <th class="py-3 px-5">Aplica a</th>
                    <th class="py-3 px-5 text-center">Descuento</th>
                    <th class="py-3 px-5 text-center">Vencimiento</th>
                    <th class="py-3 px-5 text-center">Estado</th>
                    <th class="py-3 px-5 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($promociones as $promo)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                        <td class="py-3 px-5 font-bold text-gray-800 dark:text-white">{{ $promo->prom_nombre }}</td>
                        <td class="py-3 px-5">
                            @if($promo->prom_aplica_a == 'CATEGORIA')
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs font-bold">📂 {{ $promo->categoria->cat_nombre ?? '' }}</span>
                            @else
                                <span class="px-2 py-1 bg-cyan-100 text-cyan-700 rounded text-xs font-bold">📦 {{ $promo->producto->pro_nombre ?? '' }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-5 text-center font-bold text-emerald-600">
                            {{ $promo->prom_tipo_descuento == 'PORCENTAJE' ? $promo->prom_valor . '%' : 'Gs. ' . number_format($promo->prom_valor, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-5 text-center">
                            @if($promo->prom_fecha_fin < $hoy)
                                <span class="text-red-500 font-bold flex items-center justify-center gap-1">⏱️ Expirado</span>
                            @else
                                <span class="text-gray-600 dark:text-gray-300">{{ \Carbon\Carbon::parse($promo->prom_fecha_fin)->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-5 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold {{ $promo->prom_activa ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $promo->prom_activa ? 'ACTIVA' : 'INACTIVA' }}
                            </span>
                        </td>
                       
                <td class="py-3 px-5 text-right space-x-2">
                    <form action="{{ route('promociones.toggle', $promo->prom_id) }}" method="POST" class="inline-block">
                        @csrf
                        <button type="submit" class="text-xs font-bold text-blue-600 hover:text-blue-800" title="Cambiar Estado">🔄</button>
                    </form>
                    
                    <button @click='openEdit(@json($promo))' class="text-yellow-600 hover:text-yellow-800" title="Editar">✏️</button>
                    
                    <form action="{{ route('promociones.destroy', $promo->prom_id) }}" method="POST" class="form-eliminar inline-block">
                        @csrf @method('DELETE')
                        <button type="button" class="btn-eliminar text-red-600 hover:text-red-800" title="Eliminar">❌</button>
                    </form>
                </td>

                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-400">No hay promociones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

 <!-- Modal Dinámico (Crear / Editar Promoción) -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" style="display: none;">
        <div @click.away="showModal = false" class="bg-white dark:bg-[#1c2434] w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-900/50">
                <!-- Título Dinámico -->
                <h3 class="font-bold text-gray-800 dark:text-white" x-text="editMode ? '✏️ Editar Promoción' : '🎁 Crear Nueva Promoción'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-800 text-xl">&times;</button>
            </div>
            
            <!-- Acción Dinámica -->
            <form :action="editMode ? '/promociones/' + prom_id : '{{ route('promociones.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>
                
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Nombre / Motivo</label>
                    <input type="text" name="prom_nombre" x-model="prom_nombre" placeholder="Ej: Black Friday..." class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Aplicar a</label>
                        <select name="prom_aplica_a" x-model="aplicaA" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm">
                            <option value="PRODUCTO">Producto Individual</option>
                            <option value="CATEGORIA">Categoría Completa</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Fecha Vencimiento</label>
                        <input type="date" name="prom_fecha_fin" x-model="prom_fecha_fin" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm" required>
                    </div>
                </div>

                <!-- Selectores dinámicos -->
                <div x-show="aplicaA === 'PRODUCTO'">
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Seleccionar Producto</label>
                    <select name="pro_id" x-model="pro_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm">
                        <option value="">Seleccione un producto...</option>
                        @foreach($productos as $pro)
                            <option value="{{ $pro->pro_id }}">{{ $pro->pro_codigo }} - {{ $pro->pro_nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="aplicaA === 'CATEGORIA'" style="display: none;">
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Seleccionar Categoría</label>
                    <select name="cat_id" x-model="cat_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm">
                        <option value="">Seleccione una categoría...</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->cat_id }}">{{ $cat->cat_nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1">Tipo Descuento</label>
                        <select name="prom_tipo_descuento" x-model="tipoDesc" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm">
                            <option value="PORCENTAJE">Porcentaje (%)</option>
                            <option value="MONTO">Monto Fijo (Gs.)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1" x-text="tipoDesc === 'PORCENTAJE' ? 'Valor (%)' : 'Valor (Gs.)'"></label>
                        <input type="number" name="prom_valor" x-model="prom_valor" min="1" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm" required>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-gray-800 mt-6">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition" x-text="editMode ? 'Actualizar Promoción' : 'Guardar Promoción'"></button>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-eliminar').forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('.form-eliminar');
                Swal.fire({
                    title: '¿Eliminar Promoción?',
                    text: "El precio volverá a la normalidad.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    });
</script>
@endsection