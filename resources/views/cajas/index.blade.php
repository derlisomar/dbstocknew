@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    editMode: false,
    caj_id: '',
    suc_id: '',
    caj_nombre: '',
    caj_impresora: '', // Nueva variable
    caj_activa: true,
    openCreate() {
        this.editMode = false;
        this.caj_id = '';
        this.suc_id = '';
        this.caj_nombre = '';
        this.caj_impresora = '';
        this.caj_activa = true;
        this.openModal = true;
    },
    openEdit(caja) {
        this.editMode = true;
        this.caj_id = caja.caj_id;
        this.suc_id = caja.suc_id;
        this.caj_nombre = caja.caj_nombre;
        this.caj_impresora = caja.caj_impresora;
        this.caj_activa = caja.caj_activa;
        this.openModal = true;
    }
}">
    
    <!-- Cabecera -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🧮 Gestión de Cajas</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Crea y administra las cajas registradoras por sucursal.</p>
        </div>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            <span>➕</span> Nueva Caja
        </button>
    </div>

    <!-- Alertas Flash -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-100 dark:bg-emerald-500/10 border border-emerald-400 text-emerald-700 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-500/10 border border-red-400 text-red-700 dark:text-red-400 rounded-lg">
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
                        <th class="py-4 px-6">Sucursal</th>
                        <th class="py-4 px-6">Nombre de Caja</th>
                        
                        <!-- 👇 AGREGA ESTAS DOS LÍNEAS NUEVAS 👇 -->
                        <th class="py-4 px-6">Impresora</th>
                        <th class="py-4 px-6 text-center">Tipo Impresión</th>
                        
                        <th class="py-4 px-6">Estado</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($cajas as $caja)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium">{{ $caja->caj_id }}</td>
                            <td class="py-4 px-6 font-bold text-blue-600 dark:text-blue-400">{{ $caja->sucursal->suc_nombre ?? 'N/A' }}</td>
                            <td class="py-4 px-6 font-semibold">{{ $caja->caj_nombre }}</td>

<td class="py-4 px-6">
    <span class="block text-sm font-semibold text-gray-800 dark:text-white">
        {{ $caja->caj_impresora ?? 'Sin impresora' }}
    </span>
</td>
<td class="py-4 px-6 text-center">
    @if($caja->caj_tipo_impresion === 'TICKET_FACTURA')
        <span class="inline-block px-2.5 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 text-[11px] font-bold rounded-lg border border-blue-200 dark:border-blue-800">
            🧾 FACTURA
        </span>
    @else
        <span class="inline-block px-2.5 py-1 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 text-[11px] font-bold rounded-lg border border-gray-200 dark:border-gray-700">
            🎫 TICKET COMÚN
        </span>
    @endif
</td>
                            
                            <td class="py-4 px-6 text-center">
                                @if($caja->caj_activa)
                                    <span class="px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 rounded-full">Activa</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400 rounded-full">Inactiva</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <button @click="openEdit({{ json_encode($caja) }})" class="p-2 bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg transition hover:bg-amber-200" title="Editar">
                                        ✏️
                                    </button>
                                    <button type="button" onclick="confirmarEliminacionCaja({{ $caja->caj_id }})" class="p-2 bg-red-100 dark:bg-red-500/10 text-red-600 dark:text-red-400 rounded-lg transition hover:bg-red-200" title="Borrar">
                                        ❌
                                    </button>
                                    <form id="delete-caja-{{ $caja->caj_id }}" action="{{ route('cajas.destroy', $caja->caj_id) }}" method="POST" style="display: none;">
                                        @csrf @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No hay cajas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Dinámico (Crear / Editar) -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="editMode ? '✏️ Editar Caja' : '🧮 Registrar Caja'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form :action="editMode ? '/cajas/' + caj_id : '{{ route('cajas.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Sucursal *</label>
                    <select name="suc_id" x-model="suc_id" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none" required>
                        <option value="">Seleccione una sucursal...</option>
                        @foreach($sucursales as $suc)
                            <option value="{{ $suc->suc_id }}">{{ $suc->suc_nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Nombre de la Caja *</label>
                    <input type="text" name="caj_nombre" x-model="caj_nombre" placeholder="Ej. Caja Principal 1" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none" required>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="caj_activa" name="caj_activa" x-model="caj_activa" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="caj_activa" class="text-sm font-medium text-gray-700 dark:text-gray-300">Caja Activa (Habilitada para operaciones)</label>
                </div>

                      <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Nombre / IP de la Impresora</label>
                    <input type="text" name="caj_impresora" x-model="caj_impresora" placeholder="Ej. Impresora_Caja_1" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none">
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Tipo de Impresión</label>
                    <select name="caj_tipo_impresion" x-model="caj_tipo_impresion" class="w-full rounded-xl bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-gray-800 dark:text-white outline-none focus:border-blue-500">
                        <option value="TICKET_SIMPLE">🎫 Ticket Común (Sin valor fiscal)</option>
                        <option value="TICKET_FACTURA">🧾 Ticket Factura (Con datos fiscales)</option>
                    </select>
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
    function confirmarEliminacionCaja(id) {
        Swal.fire({
            title: '¿Eliminar caja?',
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
                document.getElementById('delete-caja-' + id).submit();
            }
        })
    }
</script>
@endsection