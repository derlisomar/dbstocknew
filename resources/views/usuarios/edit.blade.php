@extends('layouts.admin')

@section('contenido')
<div class="max-w-3xl mx-auto bg-[#1c2434] border border-gray-800 rounded-lg shadow-default p-6">
    <div class="border-b border-gray-800 pb-4 mb-6">
        <h3 class="font-bold text-xl text-white">Editar Usuario</h3>
    </div>
    
    <form action="{{ route('usuarios.update', $usuario->usu_id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nombre</label>
                <input type="text" name="usu_nombre" value="{{ old('usu_nombre', $usuario->usu_nombre) }}" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Apellido</label>
                <input type="text" name="usu_apellido" value="{{ old('usu_apellido', $usuario->usu_apellido) }}" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Cédula</label>
                <input type="text" name="usu_cedula" value="{{ old('usu_cedula', $usuario->usu_cedula) }}" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Usuario de acceso</label>
                <input type="text" name="usu_usuario" value="{{ old('usu_usuario', $usuario->usu_usuario) }}" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nueva Contraseña (Opcional)</label>
                <input type="password" name="usu_password" placeholder="Dejar en blanco para mantener la actual" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Rol de Sistema</label>
                <select name="rol_id" class="w-full rounded bg-gray-900 border border-gray-700 py-2.5 px-4 text-white focus:border-blue-500 outline-none">
                    @foreach($roles as $rol)
                        <option value="{{ $rol->rol_id }}" {{ $usuario->rol_id == $rol->rol_id ? 'selected' : '' }}>
                            {{ $rol->rol_nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('usuarios.index') }}" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition">Cancelar</a>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition">Actualizar Usuario</button>
            </div>
        </div>
    </form>
</div>
@endsection