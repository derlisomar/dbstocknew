@extends('layouts.admin')

@section('contenido')
<div x-data="{ openModal: false }">
    
    <!-- Cabecera del Módulo -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-white">Gestión de Usuarios</h2>
            <p class="text-sm text-gray-400 mt-1">Administra los accesos y privilegios del personal.</p>
        </div>
        
        <!-- Botón para abrir el Modal -->
        <button @click="openModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition flex items-center gap-2 shadow-lg">
            ➕ Agregar Nuevo Usuario
        </button>
    </div>

    <!-- Mensajes de éxito -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-500/10 border border-emerald-500 text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Errores de validación -->
    @if($errors->any())
        <div class="mb-4 p-4 bg-red-500/10 border border-red-500 text-red-400 rounded-lg">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Tabla de Listado -->
    <div class="bg-[#1c2434] border border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase bg-gray-900/50">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">Nombre y Apellido</th>
                        <th class="py-4 px-6">Cédula</th>
                        <th class="py-4 px-6">Usuario</th>
                        <th class="py-4 px-6">Rol</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 text-sm text-gray-300">
                    @forelse($usuarios as $user)
                        <tr class="hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6 font-medium text-white">{{ $user->usu_id }}</td>
                            <td class="py-4 px-6">{{ $user->usu_nombre }} {{ $user->usu_apellido }}</td>
                            <td class="py-4 px-6">{{ $user->usu_cedula }}</td>
                            <td class="py-4 px-6 text-blue-400 font-medium">{{ $user->usu_usuario }}</td>
                            <td class="py-4 px-6">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                    {{ $user->rol->rol_nombre ?? 'Sin Rol' }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <!-- Botón Editar (Lápiz) -->
                                    <a href="{{ route('usuarios.edit', $user->usu_id) }}" class="p-2 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 rounded-lg transition" title="Editar">
                                        ✏️
                                    </a>
                                    
                                    <!-- Botón Borrar (X) -->
                                    <form action="{{ route('usuarios.destroy', $user->usu_id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de borrar totalmente este usuario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 bg-red-500/10 text-red-400 hover:bg-red-500/20 rounded-lg transition" title="Borrar">
                                            ❌
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">No hay usuarios registrados en el sistema.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= MODAL FLOTANTE PARA NUEVO USUARIO ================= -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        
        <div @click.away="openModal = false" class="bg-[#1c2434] border border-gray-800 w-full max-w-xl rounded-xl shadow-2xl overflow-hidden transform transition-all">
            
            <!-- Cabecera del Modal -->
            <div class="flex justify-between items-center border-b border-gray-800 px-6 py-4 bg-gray-900/50">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    👤 Registrar Nuevo Usuario
                </h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-white text-xl font-bold">&times;</button>
            </div>

            <!-- Formulario dentro del Modal -->
            <form action="{{ route('usuarios.store') }}" method="POST" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="usu_nombre" placeholder="Ej. Juan" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>

                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Apellido</label>
                        <input type="text" name="usu_apellido" placeholder="Ej. Pérez" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Cédula de Identidad</label>
                        <input type="text" name="usu_cedula" placeholder="Ej. 1234567" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>

                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Usuario de Acceso</label>
                        <input type="text" name="usu_usuario" placeholder="Ej. jperez" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Contraseña</label>
                    <input type="password" name="usu_password" placeholder="Mínimo 6 caracteres" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-400 mb-1">Rol de Sistema</label>
                    <select name="rol_id" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white text-sm focus:border-blue-500 outline-none" required>
                        <option value="">Seleccione un rol...</option>
                        @foreach(\App\Models\Rol::all() as $rol)
                            <option value="{{ $rol->rol_id }}">{{ $rol->rol_nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition text-sm font-medium">
                        Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-md">
                        Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection