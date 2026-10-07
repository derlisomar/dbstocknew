@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    search: '',
    openModal: false, editMode: false,
    pro_id: '', cat_id: '', prov_id: '', suc_id: '', dep_id: '',
    pro_codigo: '', pro_nombre: '', pro_descripcion: '',
    pro_preciocosto: 0, pro_precioventa: 0, pro_preciomayorista: 0,
    pro_stockactual: 0, pro_stockminimo: 5, pro_fechavencimiento: '',
    pro_activo: true, current_image: '',
    openCreate() {
        this.editMode = false; this.pro_id = ''; this.cat_id = ''; this.prov_id = '';
        this.suc_id = ''; this.dep_id = ''; this.pro_codigo = ''; this.pro_nombre = '';
        this.pro_descripcion = ''; this.pro_preciocosto = 0; this.pro_precioventa = 0;
        this.pro_preciomayorista = 0; this.pro_stockactual = 0; this.pro_stockminimo = 5;
        this.pro_fechavencimiento = ''; this.pro_activo = true; this.current_image = '';
        this.openModal = true;
    },
    openEdit(pro) {
        this.editMode = true; this.pro_id = pro.pro_id; this.cat_id = pro.cat_id;
        this.prov_id = pro.prov_id || ''; this.suc_id = pro.suc_id; this.dep_id = pro.dep_id;
        this.pro_codigo = pro.pro_codigo; this.pro_nombre = pro.pro_nombre;
        this.pro_descripcion = pro.pro_descripcion || ''; this.pro_preciocosto = pro.pro_preciocosto;
        this.pro_precioventa = pro.pro_precioventa; this.pro_preciomayorista = pro.pro_preciomayorista;
        this.pro_stockactual = pro.pro_stockactual; this.pro_stockminimo = pro.pro_stockminimo;
        this.pro_fechavencimiento = pro.pro_fechavencimiento || ''; this.pro_activo = pro.pro_activo;
        this.current_image = pro.pro_imagen ? '/storage/' + pro.pro_imagen : '';
        this.openModal = true;
    }
}">
    
<!-- Encabezado y Botón Nuevo -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
            📦 Gestión de Productos
        </h1>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm transition-colors flex items-center gap-2 font-medium">
            + Nuevo Producto
        </button>
    </div>

    <!-- Tarjetas de Estadísticas (KPIs) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Registrados -->
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 uppercase tracking-wide">Total Registrados</p>
                <h4 class="text-3xl font-black text-gray-800 dark:text-white mt-1">{{ $totalProductos }}</h4>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl text-blue-600 text-2xl">
                📋
            </div>
        </div>

        <!-- Sin Stock -->
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 uppercase tracking-wide">Sin Stock</p>
                <h4 class="text-3xl font-black text-red-600 mt-1">{{ $sinStock }}</h4>
            </div>
            <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl text-red-600 text-2xl">
                ⚠️
            </div>
        </div>

        <!-- Inactivos -->
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 uppercase tracking-wide">Inactivos</p>
                <h4 class="text-3xl font-black text-gray-400 mt-1">{{ $inactivos }}</h4>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl text-gray-500 text-2xl">
                🚫
            </div>
        </div>
    </div>

    <!-- Buscador de Productos -->
        <div class="mb-6">
            <div class="relative w-full max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">🔍</span>
                <input 
                    type="text" 
                    x-model="search" 
                    placeholder="Buscar producto por nombre o código..." 
                    class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c2434] py-2.5 pl-10 pr-4 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500 transition shadow-sm"
                >
            </div>
        </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b bg-gray-50 dark:bg-gray-900/50 text-xs uppercase text-gray-500">
                        <th class="py-4 px-6">Foto</th>
                        <th class="py-4 px-6">Producto</th>
                        <th class="py-4 px-6 text-center">Stock</th>
                        <th class="py-4 px-6">Precios</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @foreach($productos as $pro)
                        <tr x-show="search === '' || `{{ addslashes($pro->pro_nombre . ' ' . $pro->pro_codigo) }}`.toLowerCase().includes(search.toLowerCase())" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6">
                                @if($pro->pro_imagen)
                                    <img src="{{ asset('storage/' . $pro->pro_imagen) }}" class="h-10 w-10 object-cover rounded shadow">
                                @else
                                    <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center text-xs">Sin Foto</div>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-bold block">{{ $pro->pro_nombre }}</span>
                                <span class="text-xs text-blue-500">{{ $pro->pro_codigo }}</span>
                                @if($pro->pro_fechavencimiento)
                                    <span class="text-xs text-gray-400 block">Vence: {{ \Carbon\Carbon::parse($pro->pro_fechavencimiento)->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center font-bold">
                                @if($pro->pro_stockactual <= 0)
                                    <span class="text-red-500">{{ $pro->pro_stockactual }} (Agotado)</span>
                                @elseif($pro->pro_stockactual <= $pro->pro_stockminimo)
                                    <span class="text-orange-500">{{ $pro->pro_stockactual }} (Bajo)</span>
                                @else
                                    <span class="text-emerald-500">{{ $pro->pro_stockactual }} (Óptimo)</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-xs">
                                <div><span class="text-gray-400">Normal:</span> Gs. {{ number_format($pro->pro_precioventa, 0, ',', '.') }}</div>
                                <div><span class="text-amber-500">Mayor:</span> Gs. {{ number_format($pro->pro_preciomayorista, 0, ',', '.') }}</div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <button @click="openEdit({{ json_encode($pro) }})" class="p-2 bg-amber-100 text-amber-600 rounded-lg">✏️</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Controles de Paginación -->
        <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30">
            {{ $productos->links() }}
        </div>
        
        </div>
    </div>

    <!-- Modal Formulario -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] w-full max-w-4xl rounded-xl shadow-2xl overflow-hidden">
            <form :action="editMode ? '/productos/' + pro_id : '{{ route('productos.store') }}'" method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                @csrf <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>
                
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 border-b pb-2" x-text="editMode ? 'Editar Producto' : 'Nuevo Producto'"></h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Código *</label>
                        <input type="text" name="pro_codigo" x-model="pro_codigo" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs uppercase text-gray-500">Nombre *</label>
                        <input type="text" name="pro_nombre" x-model="pro_nombre" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Stock Actual</label>
                        <input type="number" name="pro_stockactual" x-model="pro_stockactual" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Stock Mínimo</label>
                        <input type="number" name="pro_stockminimo" x-model="pro_stockminimo" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Vencimiento (Opcional)</label>
                        <input type="date" name="pro_fechavencimiento" x-model="pro_fechavencimiento" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Imagen</label>
                        <input type="file" name="pro_imagen" accept="image/*" class="w-full text-xs mt-1 dark:text-gray-300">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Costo (Gs.) *</label>
                        <input type="number" step="0.01" name="pro_preciocosto" x-model="pro_preciocosto" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Precio Venta *</label>
                        <input type="number" step="0.01" name="pro_precioventa" x-model="pro_precioventa" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Precio Mayorista *</label>
                        <input type="number" step="0.01" name="pro_preciomayorista" x-model="pro_preciomayorista" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white p-2" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Selectores de sucursal, deposito, categoria omitidos por brevedad visual, incluye tus <select> correspondientes aquí -->
                    <select name="suc_id" x-model="suc_id" class="w-full p-2 rounded dark:bg-gray-800 dark:text-white border-gray-300"><option value="">Sucursal...</option>@foreach($sucursales as $s)<option value="{{$s->suc_id}}">{{$s->suc_nombre}}</option>@endforeach</select>
                    <select name="dep_id" x-model="dep_id" class="w-full p-2 rounded dark:bg-gray-800 dark:text-white border-gray-300"><option value="">Depósito...</option>@foreach($depositos as $d)<option value="{{$d->dep_id}}">{{$d->dep_nombre}}</option>@endforeach</select>
                    <select name="cat_id" x-model="cat_id" class="w-full p-2 rounded dark:bg-gray-800 dark:text-white border-gray-300"><option value="">Categoría...</option>@foreach($categorias as $c)<option value="{{$c->cat_id}}">{{$c->cat_nombre}}</option>@endforeach</select>
                    <select name="prov_id" x-model="prov_id" class="w-full p-2 rounded dark:bg-gray-800 dark:text-white border-gray-300"><option value="">Proveedor...</option>@foreach($proveedores as $p)<option value="{{$p->prov_id}}">{{$p->prov_razonsocial}}</option>@endforeach</select>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Clasificación de IVA</label>
                    <select name="pro_tipo_iva" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                        <option value="10">IVA 10% (General)</option>
                        <option value="5">IVA 5% (Canasta / Agro)</option>
                        <option value="0">Exento</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-2 mt-4 pt-2">
                    <input type="checkbox" id="pro_activo" name="pro_activo" x-model="pro_activo" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="pro_activo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Producto Activo (Disponible para la venta)</label>
                </div>

                <div class="flex justify-end gap-3 border-t mt-4 pt-4">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 rounded-lg text-gray-800">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow-md">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection