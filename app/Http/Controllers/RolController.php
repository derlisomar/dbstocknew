<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index()
    {
        // Traemos todos los roles con sus permisos asignados
        $roles = Rol::with('permisos')->orderBy('rol_id', 'asc')->get();
        
        // Traemos todos los permisos pero AGRUPADOS por módulo (ej: USUARIOS, CAJAS, VENTAS) para el diseño visual
        $permisosAgrupados = Permiso::all()->groupBy('perm_modulo');

        return view('roles.index', compact('roles', 'permisosAgrupados'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'rol_nombre' => 'required|string|max:50|unique:roles,rol_nombre',
            'rol_descripcion' => 'nullable|string|max:150',
            'permisos' => 'nullable|array' // Array de IDs de permisos marcados
        ]);

        $rol = Rol::create([
            'rol_nombre' => $request->rol_nombre,
            'rol_descripcion' => $request->rol_descripcion
        ]);

        // Sincronizar los permisos marcados en la tabla intermedia 'rol_permisos'
        if ($request->has('permisos')) {
            $rol->permisos()->sync($request->permisos);
        }

        return redirect()->route('roles.index')->with('success', 'Rol y permisos registrados correctamente.');
    }

    public function update(Request $request, $id)
    {
        $rol = Rol::findOrFail($id);

        $request->validate([
            'rol_nombre' => 'required|string|max:50|unique:roles,rol_nombre,' . $id . ',rol_id',
            'rol_descripcion' => 'nullable|string|max:150',
            'permisos' => 'nullable|array'
        ]);

        $rol->update([
            'rol_nombre' => $request->rol_nombre,
            'rol_descripcion' => $request->rol_descripcion
        ]);

        // Actualizar automáticamente los accesos (agrega los nuevos y borra los desmarcados)
        $rol->permisos()->sync($request->permisos ?? []);

        return redirect()->route('roles.index')->with('success', 'Rol y privilegios actualizados.');
    }

    public function destroy($id)
    {
        $rol = Rol::findOrFail($id);
        // Laravel borra automáticamente la relación en 'rol_permisos' si configuras cascada en DB, 
        // pero por seguridad lo hacemos manual:
        $rol->permisos()->detach(); 
        $rol->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }
}