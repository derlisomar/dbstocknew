<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use App\Services\AuditoriaService;
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

    public function store(Request $request)
    {
        $request->validate([
            'usu_nombre' => 'required|string|max:100',
            'usu_apellido' => 'required|string|max:100',
            'usu_cedula' => 'required|string|max:20|unique:usuarios,usu_cedula',
            'usu_usuario' => 'required|string|max:50|unique:usuarios,usu_usuario',
            'usu_email' => 'required|email|max:150|unique:usuarios,usu_email', // <- NUEVO
            'usu_password' => 'required|string|min:6',
            'rol_id' => 'required|exists:roles,rol_id',
        ]);

        $this->protegerAdministradores(null, (int) $request->rol_id);

        User::create([
            'usu_nombre' => $request->usu_nombre,
            'usu_apellido' => $request->usu_apellido,
            'usu_cedula' => $request->usu_cedula,
            'usu_usuario' => $request->usu_usuario,
            'usu_email' => $request->usu_email, // <- NUEVO
            'usu_password' => Hash::make($request->usu_password),
            'rol_id' => $request->rol_id,
            'usu_activo' => true,
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        $this->protegerAdministradores($usuario, (int) $request->rol_id);

        $request->validate([
            'usu_nombre' => 'required|string|max:100',
            'usu_apellido' => 'required|string|max:100',
            'usu_cedula' => 'required|string|max:20|unique:usuarios,usu_cedula,' . $id . ',usu_id',
            'usu_usuario' => 'required|string|max:50|unique:usuarios,usu_usuario,' . $id . ',usu_id',
            'usu_email' => 'required|email|max:150|unique:usuarios,usu_email,' . $id . ',usu_id', // <- NUEVO
            'rol_id' => 'required|exists:roles,rol_id',
        ]);

        $usuario->usu_nombre = $request->usu_nombre;
        $usuario->usu_apellido = $request->usu_apellido;
        $usuario->usu_cedula = $request->usu_cedula;
        $usuario->usu_usuario = $request->usu_usuario;
        $usuario->usu_email = $request->usu_email; // <- NUEVO
        $usuario->rol_id = $request->rol_id;

        if ($request->filled('usu_password')) {
            $usuario->usu_password = Hash::make($request->usu_password);
        }

        $usuario->save();

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    // 2. Mostrar formulario de creación
    public function create()
    {
        $roles = Rol::all();
        return view('usuarios.create', compact('roles'));
    }



    // 4. Mostrar formulario de edición
    public function edit($id)
    {
        $usuario = User::findOrFail($id);
        $roles = Rol::all();
        return view('usuarios.edit', compact('usuario', 'roles'));
    }


    // 6. Desactivar al usuario (no se borra: se conserva su historial de ventas y cajas)
    public function destroy($id)
    {
        $usuario = User::findOrFail($id);

        if ((int) $usuario->usu_id === (int) auth()->id()) {
            return redirect()->route('usuarios.index')->with('error', 'No podés desactivar tu propio usuario.');
        }

        $this->protegerAdministradores($usuario);

        $usuario->update(['usu_activo' => false]);
        AuditoriaService::registrar('USUARIO_DESACTIVADO', 'usuarios', $usuario->usu_id, ['usuario' => $usuario->usu_usuario]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario desactivado: ya no puede iniciar sesión y su historial se conserva.');
    }

    // Volver a habilitar a un usuario desactivado
    public function reactivar($id)
    {
        $usuario = User::findOrFail($id);

        $this->protegerAdministradores($usuario);

        $usuario->update(['usu_activo' => true]);
        AuditoriaService::registrar('USUARIO_REACTIVADO', 'usuarios', $usuario->usu_id, ['usuario' => $usuario->usu_usuario]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario reactivado: ya puede iniciar sesión.');
    }

    /**
     * Solo un administrador puede modificar a otro administrador o asignar el rol de administrador.
     * Evita que alguien con permiso de "gestionar usuarios" se otorgue acceso total.
     */
    private function protegerAdministradores(?User $objetivo, ?int $rolIdNuevo = null): void
    {
        if (auth()->user()->esAdministrador()) {
            return;
        }

        if ($objetivo && $objetivo->esAdministrador()) {
            abort(403, 'Solo un administrador puede modificar a otro administrador.');
        }

        if ($rolIdNuevo) {
            $rol = Rol::find($rolIdNuevo);
            $admins = array_map('mb_strtolower', config('permisos.roles_admin', []));

            if ($rol && in_array(mb_strtolower($rol->rol_nombre), $admins, true)) {
                abort(403, 'Solo un administrador puede asignar el rol de administrador.');
            }
        }
    }
}
