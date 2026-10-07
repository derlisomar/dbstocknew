@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    editMode: false,
    dep_id: '',
    suc_id: '',
    dep_nombre: '',
    dep_descripcion: '',
    dep_activo: true,
    openCreate() {
        this.editMode = false;
        this.dep_id = '';
        this.suc_id = '';
        this.dep_nombre = '';
        this.dep_descripcion = '';
        this.dep_activo = true;
        this.openModal = true;
    },
    openEdit(deposito) {
        this.editMode = true;
        this.dep_id = deposito.dep_id;
        this.suc_id = deposito.suc_id;
        this.dep_nombre = deposito.dep_nombre;
        this.dep_descripcion = deposito.dep_descripcion || '';
        this.dep_activo = deposito.dep_activo;
        this.openModal = true;
    }
}">
    
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🏢 Gestión de Depósitos</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra los depósitos físicos asociados a cada sucursal.</p>
        </div>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            <span>➕</span> Nuevo Depósito
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-100 dark:bg-emerald-500/10 border border-emerald-400 dark:border-emerald-500 text-emerald-700 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Sucursal</th>
                        <th class="py-4 px-6">Depósito</th>
                        <th class="py-4 px-6">Descripción</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($depositos as $dep)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium">{{ $dep->dep_id }}</td>
                            <td class="py-4 px-6 font-bold text-blue-600 dark:text-blue-400">{{ $dep->sucursal->suc_nombre ?? 'N/A' }}</td>
                            <td class="py-4 px-6 font-semibold">{{ $dep->dep_nombre }}</td>
                            <td class="py-4 px-6">{{ $dep->dep_descripcion ?? '-' }}</td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <button @click="openEdit({{ json_encode($dep) }})" class="p-2 bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg transition hover:bg-amber-200" title="Editar">✏️</button>
                                    <button type="button" onclick="confirmarEliminacionDeposito({{ $dep->dep_id }})" class="p-2 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-lg transition hover:bg-red-200" title="Borrar">❌</button>
                                    <form id="delete-dep-{{ $dep->dep_id }}" action="{{ route('depositos.destroy', $dep->dep_id) }}" method="POST" style="display: none;">
                                        @csrf @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No hay depósitos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear/Editar Depósito -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="editMode ? '✏️ Editar Depósito' : '🏢 Registrar Depósito'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form :action="editMode ? '/depositos/' + dep_id : '{{ route('depositos.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Sucursal *</label>
                    <select name="suc_id" x-model="suc_id" class="w-full rounded bg-white dark:bg-[#1c2434] border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none" required>
                        <option value="">Seleccione Sucursal...</option>
                        @foreach($sucursales as $suc)
                            <option value="{{ $suc->suc_id }}">{{ $suc->suc_nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Nombre del Depósito *</label>
                    <input type="text" name="dep_nombre" x-model="dep_nombre" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none" required>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                    <textarea name="dep_descripcion" x-model="dep_descripcion" rows="2" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow-md" x-text="editMode ? 'Actualizar' : 'Guardar'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmarEliminacionDeposito(id) {
        Swal.fire({
            title: '¿Eliminar depósito?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: document.documentElement.classList.contains('dark') ? '#1c2434' : '#ffffff',
            color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000'
        }).then((result) => {
            if (result.isConfirmed) { document.getElementById('delete-dep-' + id).submit(); }
        })
    }
</script>
@endsection