@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    editMode: false,
    suc_id: '',
    suc_nombre: '',
    suc_direccion: '',
    suc_telefono: '',
    suc_activa: true,
    suc_actividad_economica: '',
    suc_timbrado: '',
    suc_est_punto_exp: '',
    suc_timbrado_inicio: '',
    suc_timbrado_fin: '',

    openCreate() {
        this.editMode = false;
        this.suc_id = '';
        this.suc_nombre = '';
        this.suc_direccion = '';
        this.suc_telefono = '';
        this.suc_activa = true;

        this.suc_actividad_economica = '';
        this.suc_timbrado = '';
        this.suc_est_punto_exp = '';
        this.suc_timbrado_inicio = '';
        this.suc_timbrado_fin = '';

        this.openModal = true;
    },
    openEdit(sucursal) {
        this.editMode = true;
        this.suc_id = sucursal.suc_id;
        this.suc_nombre = sucursal.suc_nombre;
        this.suc_direccion = sucursal.suc_direccion || '';
        this.suc_telefono = sucursal.suc_telefono || '';
        this.suc_activa = sucursal.suc_activa;

        this.suc_actividad_economica = sucursal.suc_actividad_economica || '';
        this.suc_timbrado = sucursal.suc_timbrado || '';
        this.suc_est_punto_exp = sucursal.suc_est_punto_exp || '';
        this.suc_timbrado_inicio = sucursal.suc_timbrado_inicio || '';
        this.suc_timbrado_fin = sucursal.suc_timbrado_fin || '';

        this.openModal = true;
    }
}">
    
    <!-- Cabecera -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🏢 Gestión de Sucursales</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configuración y administración de ubicaciones físicas.</p>
        </div>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            <span>➕</span> Nueva Sucursal
        </button>
    </div>

    <!-- Alertas Flash -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-100 dark:bg-emerald-500/10 border border-emerald-400 dark:border-emerald-500 text-emerald-700 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-500/10 border border-red-400 dark:border-red-500 text-red-700 dark:text-red-400 rounded-lg">
            <ul>
                @foreach ($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <!-- Tabla de Listado -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Nombre de Sucursal</th>
                        <th class="py-4 px-6">Teléfono</th>
                        <th class="py-4 px-6">Dirección</th>
                        <th class="py-4 px-6">Punto Exp. / Timbrado</th>
                        <th class="py-4 px-6">Estado</th>
                        <th class="py-4 px-6 text-center">Estado</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($sucursales as $suc)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium">{{ $suc->suc_id }}</td>
                            <td class="py-4 px-6 font-semibold">{{ $suc->suc_nombre }}</td>
                            <td class="py-4 px-6">{{ $suc->suc_telefono ?? '-' }}</td>
                            <td class="py-4 px-6 truncate max-w-xs">{{ $suc->suc_direccion ?? '-' }}</td>

                            <td class="py-4 px-6">
                                <span class="block font-bold text-gray-800 dark:text-white">{{ $suc->suc_est_punto_exp ?? 'S/N' }}</span>
                                <span class="text-xs text-gray-500">Timb: {{ $suc->suc_timbrado ?? '---' }}</span>
                            </td>

                            <td class="py-4 px-6 text-center">
                                @if($suc->suc_activa)
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">Activa</span>
                                @else
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/20">Inactiva</span>
                                @endif
                            </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                <button @click='openEdit(@json($suc))' class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Editar Sucursal">
                                    ✏️
                                </button>
                                    
                                    <form action="{{ route('sucursales.destroy', $suc->suc_id) }}" method="POST" class="form-eliminar inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn-eliminar inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition" title="Eliminar Sucursal">
                                                ❌
                                            </button>
                                    </form>

                                    </div>
                                </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">No hay sucursales registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Nueva/Editar Sucursal -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-xl rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="editMode ? '✏️ Editar Sucursal' : '🏢 Registrar Sucursal'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form :action="editMode ? '/sucursales/' + suc_id : '{{ route('sucursales.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Nombre de la Sucursal *</label>
                    <input type="text" name="suc_nombre" x-model="suc_nombre" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none" required>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Teléfono de Contacto</label>
                    <input type="text" name="suc_telefono" x-model="suc_telefono" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Dirección Completa</label>
                    <textarea name="suc_direccion" x-model="suc_direccion" rows="3" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Actividad Económica (Para Factura)</label>
                        <input type="text" name="suc_actividad_economica" x-model="suc_actividad_economica" placeholder="Ej. COMERCIO AL POR MAYOR..." class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Nro. Timbrado</label>
                        <input type="text" name="suc_timbrado" x-model="suc_timbrado" placeholder="Ej. 12345678" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Punto de Expedición</label>
                        <input type="text" name="suc_est_punto_exp" x-model="suc_est_punto_exp" placeholder="Ej. 001-001" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Inicio Vigencia</label>
                        <input type="date" name="suc_timbrado_inicio" x-model="suc_timbrado_inicio" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Fin Vigencia</label>
                        <input type="date" name="suc_timbrado_fin" x-model="suc_timbrado_fin" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3">
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-2">
                    <input type="checkbox" name="suc_activa" id="suc_activa" x-model="suc_activa" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="suc_activa" class="text-sm font-medium text-gray-700 dark:text-gray-300">Sucursal Operativa (Activa)</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-md" x-text="editMode ? 'Actualizar Sucursal' : 'Guardar Sucursal'"></button>
                </div>
            </form>
        </div>
    </div>


</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Capturar todos los botones de eliminar
        const botonesEliminar = document.querySelectorAll('.btn-eliminar');
        
        botonesEliminar.forEach(boton => {
            boton.addEventListener('click', function(e) {
                e.preventDefault(); // Evita que se borre directamente
                const form = this.closest('.form-eliminar');
                
                // Mostrar la alerta de confirmación
                Swal.fire({
                    title: '¿Eliminar Sucursal?',
                    html: "Si eliminas esta sucursal, <b>también se eliminarán o afectarán todas las cajas relacionadas</b> a ella.<br><br><i>Te recomendamos eliminar las cajas de esta sucursal primero antes de continuar.</i>",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar sucursal',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit(); // Solo envía el formulario si el usuario confirma
                    }
                });
            });
        });
        
        // Alertas de éxito o error que vienen del controlador
        @if(session('success'))
            Swal.fire('¡Éxito!', '{{ session('success') }}', 'success');
        @endif
        @if(session('error'))
            Swal.fire('Error', '{{ session('error') }}', 'error');
        @endif
    });
</script>
@endsection