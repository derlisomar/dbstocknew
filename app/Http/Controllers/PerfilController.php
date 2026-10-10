<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Cada usuario edita sus propios datos y su contraseña. No necesita ningún permiso especial. */
class PerfilController extends Controller
{
    public function edit()
    {
        $usuario = auth()->user()->load('rol');

        return view('perfil.edit', ['usuario' => $usuario]);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'usu_nombre' => ['required', 'string', 'max:100'],
            'usu_apellido' => ['nullable', 'string', 'max:100'],
            'usu_email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'usu_email')->ignore($usuario->usu_id, 'usu_id')],
        ], [
            'usu_nombre.required' => 'Escribí tu nombre.',
            'usu_email.required' => 'Escribí tu correo.',
            'usu_email.email' => 'Revisá el correo, parece incompleto.',
            'usu_email.unique' => 'Ese correo ya lo usa otro usuario.',
        ]);

        $usuario->usu_nombre = trim($datos['usu_nombre']);
        $usuario->usu_apellido = isset($datos['usu_apellido']) ? trim($datos['usu_apellido']) : null;
        $usuario->usu_email = trim($datos['usu_email']);
        $usuario->save();

        AuditoriaService::registrar('PERFIL_ACTUALIZADO', 'usuarios', $usuario->usu_id, ['usuario' => $usuario->usu_usuario]);

        return redirect()->route('perfil.edit')->with('ok_perfil', 'Guardamos tus datos.');
    }

    public function clave(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $request->validate([
            'clave_actual' => ['required', 'string'],
            'clave_nueva' => ['required', 'string', 'min:8', 'confirmed', 'different:clave_actual'],
        ], [
            'clave_actual.required' => 'Escribí tu contraseña actual.',
            'clave_nueva.required' => 'Escribí la contraseña nueva.',
            'clave_nueva.min' => 'La contraseña nueva debe tener al menos 8 caracteres.',
            'clave_nueva.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
            'clave_nueva.different' => 'La contraseña nueva tiene que ser distinta a la actual.',
        ]);

        if (! Hash::check($request->input('clave_actual'), $usuario->usu_password)) {
            return redirect()->route('perfil.edit')->withErrors(['clave_actual' => 'La contraseña actual no es correcta.'])->withFragment('clave');
        }

        $usuario->usu_password = Hash::make($request->input('clave_nueva'));
        $usuario->save();

        AuditoriaService::registrar('CLAVE_CAMBIADA', 'usuarios', $usuario->usu_id, ['usuario' => $usuario->usu_usuario]);

        return redirect()->route('perfil.edit')->with('ok_clave', 'Cambiamos tu contraseña.')->withFragment('clave');
    }
}
