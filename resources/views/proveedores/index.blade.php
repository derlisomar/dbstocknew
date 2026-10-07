@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    editMode: false,
    prov_id: '',
    prov_razonsocial: '',
    prov_ruc: '',
    prov_telefono: '',
    prov_email: '',
    prov_direccion: '',
    openCreate() {
        this.editMode = false;
        this.prov_id = '';
        this.prov_razonsocial = '';
        this.prov_ruc = '';
        this.prov_telefono = '';
        this.prov_email = '';
        this.prov_direccion = '';
        this.openModal = true;
    },
    openEdit(proveedor) {
        this.editMode = true;
        this.prov_id = proveedor.prov_id;
        this.prov_razonsocial = proveedor.prov_razonsocial;
        this.prov_ruc = proveedor.prov_ruc || '';
        this.prov_telefono = proveedor.prov_telefono || '';
        this.prov_email = proveedor.prov_email || '';
        this.prov_direccion = proveedor.prov_direccion || '';
        this.openModal = true;
    }
}">
    
    <!-- Cabecera -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🚚 Gestión de Proveedores</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra los proveedores y sus datos de contacto.</p>
        </div>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            <span>➕</span> Nuevo Proveedor
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
                        <th class="py-4 px-6">Razón Social</th>
                        <th class="py-4 px-6">RUC</th>
                        <th class="py-4 px-6">Teléfono</th>
                        <th class="py-4 px-6">Email</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($proveedores as $prov)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium">{{ $prov->prov_id }}</td>
                            <td class="py-4 px-6 font-semibold">{{ $prov->prov_razonsocial }}</td>
                            <td class="py-4 px-6">{{ $prov->prov_ruc ?? '-' }}</td>
                            <td class="py-4 px-6">{{ $prov->prov_telefono ?? '-' }}</td>
                            <td class="py-4 px-6">{{ $prov->prov_email ?? '-' }}</td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <button @click="openEdit({{ json_encode($prov) }})" class="p-2 bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg transition hover:bg-amber-200" title="Editar">
                                        ✏️
                                    </button>
                                    <button type="button" onclick="confirmarEliminacion({{ $prov->prov_id }})" class="p-2 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-lg transition hover:bg-red-200" title="Borrar">
                                        ❌
                                    </button>
                                    <form id="delete-form-{{ $prov->prov_id }}" action="{{ route('proveedores.destroy', $prov->prov_id) }}" method="POST" style="display: none;">
                                        @csrf @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">No hay proveedores registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Nuevo/Editar Proveedor -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="editMode ? '✏️ Editar Proveedor' : '🚚 Registrar Proveedor'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form :action="editMode ? '/proveedores/' + prov_id : '{{ route('proveedores.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Razón Social *</label>
                        <input type="text" name="prov_razonsocial" x-model="prov_razonsocial" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">RUC</label>
                        <input type="text" name="prov_ruc" x-model="prov_ruc" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Teléfono</label>
                        <input type="text" name="prov_telefono" x-model="prov_telefono" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Correo Electrónico</label>
                        <input type="email" name="prov_email" x-model="prov_email" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Dirección Física</label>
                    <textarea name="prov_direccion" x-model="prov_direccion" rows="3" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white focus:border-blue-500 outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-md" x-text="editMode ? 'Actualizar Proveedor' : 'Guardar Proveedor'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmarEliminacion(id) {
        Swal.fire({
            title: '¿Eliminar proveedor?',
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
            if (result.isConfirmed) {
                document.getElementById('delete-form-' + id).submit();
            }
        })
    }
</script>
@endsection