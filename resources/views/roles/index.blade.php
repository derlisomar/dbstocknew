@extends('layouts.admin')

@section('contenido')
<div class="space-y-6" x-data="{ 
    openModal: false, 
    editMode: false, 
    rol_id: '', 
    rol_nombre: '', 
    rol_descripcion: '', 
    permisosSeleccionados: [],

    openCreate() {
        this.editMode = false;
        this.rol_id = '';
        this.rol_nombre = '';
        this.rol_descripcion = '';
        this.permisosSeleccionados = [];
        this.openModal = true;
    },

    openEdit(rol) {
        this.editMode = true;
        this.rol_id = rol.rol_id;
        this.rol_nombre = rol.rol_nombre;
        this.rol_descripcion = rol.rol_descripcion;
        // Extraemos solo los IDs de los permisos que tiene el rol para marcarlos en los checkboxes
        this.permisosSeleccionados = rol.permisos.map(p => p.perm_id.toString());
        this.openModal = true;
    }
}">
    
    <!-- Cabecera -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Gestión de Roles y Privilegios</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Crea roles y asigna a qué módulos del sistema pueden acceder.</p>
        </div>
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            <span>➕</span> Nuevo Rol
        </button>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500 text-emerald-600 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Tabla de Roles -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">NOMBRE DEL ROL</th>
                        <th class="py-4 px-6">DESCRIPCIÓN</th>
                        <th class="py-4 px-6">CANT. ACCESOS</th>
                        <th class="py-4 px-6 text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($roles as $rol)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium">{{ $rol->rol_id }}</td>
                            <td class="py-4 px-6 font-bold text-blue-600 dark:text-blue-400">{{ $rol->rol_nombre }}</td>
                            <td class="py-4 px-6">{{ $rol->rol_descripcion }}</td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs font-semibold">{{ $rol->permisos->count() }} Permisos</span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click='openEdit(@json($rol))' class="p-2 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-100 rounded-lg transition" title="Modificar Accesos">✏️</button>
                                    <form action="{{ route('roles.destroy', $rol->rol_id) }}" method="POST" onsubmit="return confirm('¿Eliminar este rol? Los usuarios con este rol perderán acceso.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-2 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 rounded-lg transition">❌</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No hay roles registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL FLOTANTE: FORMULARIO Y CHECKBOXES -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-5xl rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="flex justify-between items-center px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-800">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <span x-text="editMode ? '✏️ Editar Rol y Accesos' : '🛡️ Registrar Nuevo Rol'"></span>
                </h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
            </div>

            <form :action="editMode ? '/roles/' + rol_id : '{{ route('roles.store') }}'" method="POST" class="flex-1 overflow-y-auto p-6 space-y-6">
                @csrf
                <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                <!-- Datos del Rol -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Nombre del Rol</label>
                        <input type="text" name="rol_nombre" x-model="rol_nombre" placeholder="Ej. Cajero, Vendedor..." class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2 px-4 text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                        <input type="text" name="rol_descripcion" x-model="rol_descripcion" placeholder="Breve descripción del cargo..." class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2 px-4 text-gray-800 dark:text-white outline-none focus:border-blue-500">
                    </div>
                </div>

<!-- Selector de Permisos Agrupados -->
                <div class="mt-6">
                    <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-800 pb-2 mb-4 flex items-center gap-2">
                        🔑 Asignación de Privilegios
                    </h4>
                    
                    @if($permisosAgrupados->isEmpty())
                        <!-- DISEÑO ESTÉTICO CUANDO LA BASE DE DATOS ESTÁ VACÍA -->
                        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/50 rounded-2xl p-8 text-center shadow-inner">
                            <div class="text-5xl mb-4 opacity-80">🛡️</div>
                            <h5 class="text-lg font-black text-blue-800 dark:text-blue-400 mb-2">Aún no hay privilegios creados</h5>
                            <p class="text-sm text-blue-600 dark:text-blue-300 max-w-sm mx-auto leading-relaxed">
                                Para asignar accesos, primero debes insertar los permisos en tu base de datos (Ej: "CREAR_VENTA", "VER_CAJAS"). 
                                Al hacerlo, aparecerán aquí automáticamente listos para ser asignados.
                            </p>
                        </div>
                    @else
                        <!-- DISEÑO ESTÉTICO CON TARJETAS Y SWITCHES (ESTILO iOS) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            @foreach($permisosAgrupados as $modulo => $permisos)
                                <div class="bg-white dark:bg-[#1a222c] p-5 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-shadow">
                                    
                                    <!-- Cabecera de la Tarjeta del Módulo -->
                                    <div class="flex items-center gap-3 mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">
                                        <div class="p-2 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                        </div>
                                        <h5 class="text-xs font-black text-gray-800 dark:text-white uppercase tracking-widest">{{ $modulo }}</h5>
                                    </div>
                                    
                                    <!-- Lista de Interruptores -->
                       <div class="space-y-4 mt-3">
                            @foreach($permisos as $permiso)
                                <label class="flex items-center justify-between cursor-pointer group">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors w-3/4 leading-tight">
                                        {{ $permiso->perm_descripcion }}
                                    </span>
                                    
                                    <!-- Switch Toggle Animado -->
                                    <div class="relative inline-flex items-center flex-shrink-0 cursor-pointer">
                                        <!-- El x-model vincula automáticamente el interruptor con la base de datos -->
                                        <input type="checkbox" name="permisos[]" value="{{ $permiso->perm_id }}" x-model="permisosSeleccionados" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 shadow-inner"></div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                                    
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" @click="openModal = false" class="px-5 py-2.5 font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition" x-text="editMode ? 'Actualizar Accesos' : 'Guardar Rol'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection