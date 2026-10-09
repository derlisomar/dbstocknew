@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openModal: false, 
    editMode: false, 
    usu_id: '', 
    usu_nombre: '', 
    usu_apellido: '', 
    usu_cedula: '', 
    usu_usuario: '', 
    usu_email: '', 
    rol_id: '',

    openCreate() {
        this.editMode = false;
        this.usu_id = '';
        this.usu_nombre = '';
        this.usu_apellido = '';
        this.usu_cedula = '';
        this.usu_usuario = '';
        this.usu_email = '';
        this.rol_id = '';
        this.openModal = true;
    },

    openEdit(user) {
        this.editMode = true;
        this.usu_id = user.usu_id;
        this.usu_nombre = user.usu_nombre;
        this.usu_apellido = user.usu_apellido;
        this.usu_cedula = user.usu_cedula;
        this.usu_usuario = user.usu_usuario;
        this.usu_email = user.usu_email;
        this.rol_id = user.rol_id;
        this.openModal = true;
    }
}">
    
    <!-- Cabecera del Módulo -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Gestión de Usuarios</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra los accesos y privilegios del personal.</p>
        </div>
        
        <button @click="openCreate()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg flex items-center gap-2">
            ➕ Agregar Nuevo Usuario
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500 text-emerald-600 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500 text-red-600 dark:text-red-400 rounded-lg">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- CONTENEDOR DE LA TABLA (Corregido: bg-white en claro, #1c2434 en oscuro) -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Nombre y Apellido</th>
                        <th class="py-4 px-6">Correo Electrónico</th>
                        <th class="py-4 px-6">Usuario</th>
                        <th class="py-4 px-6">Rol</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($usuarios as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="py-4 px-6 font-medium">{{ $user->usu_id }}</td>
                            <td class="py-4 px-6 font-semibold">{{ $user->usu_nombre }} {{ $user->usu_apellido }}</td>
                            <td class="py-4 px-6">{{ $user->usu_email }}</td>
                            <td class="py-4 px-6 text-blue-600 dark:text-blue-400 font-medium">{{ $user->usu_usuario }}</td>
                            <td class="py-4 px-6">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                    {{ $user->rol->rol_nombre ?? 'Sin Rol' }}
                                </span>
                                @if(!$user->usu_activo)
                                    <span class="ml-2 px-2 py-1 text-xs font-bold rounded-full bg-gray-200 text-gray-600">DESACTIVADO</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <button @click='openEdit(@json($user))' class="p-2 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-500/20 rounded-lg transition" title="Editar">
                                        ✏️
                                    </button>
                                    
                                    @if($user->usu_activo)
                                        <form action="{{ route('usuarios.destroy', $user->usu_id) }}" method="POST" onsubmit="return confirm('¿Desactivar este usuario? Ya no podrá iniciar sesión (su historial se conserva).');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 rounded-lg transition" title="Desactivar">
                                                ⛔
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('usuarios.reactivar', $user->usu_id) }}" method="POST" onsubmit="return confirm('¿Reactivar este usuario?');">
                                            @csrf
                                            <button type="submit" class="p-2 bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-500/20 rounded-lg transition" title="Reactivar">
                                                ✅
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= MODAL FLOTANTE (ESTÉTICO Y RESPONSIVO) ================= -->
    <div x-show="openModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="openModal = false" x-transition.scale class="bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            
            <div class="flex justify-between items-center px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg" x-text="editMode ? '✏️' : '👤'"></span>
                    <span x-text="editMode ? 'Editar Usuario' : 'Registrar Nuevo Usuario'"></span>
                </h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form :action="editMode ? '/usuarios/' + usu_id : '{{ route('usuarios.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="usu_nombre" x-model="usu_nombre" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Apellido</label>
                        <input type="text" name="usu_apellido" x-model="usu_apellido" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Cédula</label>
                        <input type="text" name="usu_cedula" x-model="usu_cedula" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Correo Electrónico</label>
                        <input type="email" name="usu_email" x-model="usu_email" placeholder="ejemplo@correo.com" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Usuario de Acceso</label>
                        <input type="text" name="usu_usuario" x-model="usu_usuario" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Contraseña</label>
                        <input type="password" name="usu_password" :placeholder="editMode ? 'Dejar en blanco para no cambiar' : 'Mínimo 6 caracteres'" :required="!editMode" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-1">Rol de Sistema</label>
                    <select name="rol_id" x-model="rol_id" class="w-full rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-gray-800 dark:text-white text-sm focus:border-blue-500 outline-none" required>
                        <option value="">Seleccione un rol...</option>
                        @foreach(\App\Models\Rol::all() as $rol)
                            <option value="{{ $rol->rol_id }}">{{ $rol->rol_nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-5 py-2.5 font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition-colors" x-text="editMode ? 'Guardar Cambios' : 'Registrar Usuario'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection