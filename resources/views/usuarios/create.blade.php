@extends('layouts.admin')

@section('contenido')
<div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
    <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
        <h3 class="font-medium text-black dark:text-white">
            Registrar Nuevo Usuario
        </h3>
    </div>
    
    <form action="{{ route('usuarios.store') }}" method="POST">
        @csrf
        <div class="p-6.5">
            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Nombre</label>
                <input type="text" name="usu_nombre" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" required>
            </div>

            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Apellido</label>
                <input type="text" name="usu_apellido" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" required>
            </div>

            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Cédula</label>
                <input type="text" name="usu_cedula" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" required>
            </div>

            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Usuario de acceso</label>
                <input type="text" name="usu_usuario" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" required>
            </div>

            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Contraseña</label>
                <input type="password" name="usu_password" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" required>
            </div>

            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">Rol de Sistema</label>
                <select name="rol_id" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
                    @foreach($roles as $rol)
                        <option value="{{ $rol->rol_id }}">{{ $rol->rol_nombre }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="flex w-full justify-center rounded bg-primary p-3 font-medium text-gray hover:bg-opacity-90">
                Guardar Usuario
            </button>
        </div>
    </form>
</div>
@endsection