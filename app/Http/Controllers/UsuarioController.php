<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    // 1. Listar todos los usuarios en una tabla
    public function index()
    {
        $usuarios = User::with('rol')->get();
        return view('usuarios.index', compact('usuarios'));
    }

    // 2. Mostrar formulario de creación
    public function create()
    {
        $roles = Rol::all();
        return view('usuarios.create', compact('roles'));
    }

    // 3. Guardar el nuevo usuario en la base de datos
    public function store(Request $request)
    {
        $request->validate([
            'usu_nombre' => 'required|string|max:100',
            'usu_apellido' => 'required|string|max:100',
            'usu_cedula' => 'required|string|max:20|unique:usuarios,usu_cedula',
            'usu_usuario' => 'required|string|max:50|unique:usuarios,usu_usuario',
            'usu_password' => 'required|string|min:6',
            'rol_id' => 'required|exists:roles,rol_id',
        ]);

        User::create([
            'usu_nombre' => $request->usu_nombre,
            'usu_apellido' => $request->usu_apellido,
            'usu_cedula' => $request->usu_cedula,
            'usu_usuario' => $request->usu_usuario,
            'usu_password' => Hash::make($request->usu_password),
            'rol_id' => $request->rol_id,
            'usu_activo' => true,
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado correctamente.');
    }

    // 4. Mostrar formulario de edición
    public function edit($id)
    {
        $usuario = User::findOrFail($id);
        $roles = Rol::all();
        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    // 5. Actualizar los datos del usuario
    public function update(Request $request, $id)
    {
        $usuario = User::findOrFail($id);

        $request->validate([
            'usu_nombre' => 'required|string|max:100',
            'usu_apellido' => 'required|string|max:100',
            'usu_cedula' => 'required|string|max:20|unique:usuarios,usu_cedula,' . $id . ',usu_id',
            'usu_usuario' => 'required|string|max:50|unique:usuarios,usu_usuario,' . $id . ',usu_id',
            'rol_id' => 'required|exists:roles,rol_id',
        ]);

        $usuario->usu_nombre = $request->usu_nombre;
        $usuario->usu_apellido = $request->usu_apellido;
        $usuario->usu_cedula = $request->usu_cedula;
        $usuario->usu_usuario = $request->usu_usuario;
        $usuario->rol_id = $request->rol_id;

        // Si ingresa una nueva contraseña, la actualizamos encriptada
        if ($request->filled('usu_password')) {
            $usuario->usu_password = Hash::make($request->usu_password);
        }

        $usuario->save();

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    // 6. Borrar el usuario totalmente de la base de datos
    public function destroy($id)
    {
        $usuario = User::findOrFail($id);
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}