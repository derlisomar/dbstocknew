<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    public function index()
    {
        $sucursales = Sucursal::orderBy('suc_id', 'asc')->get();
        return view('sucursales.index', compact('sucursales'));
    }

    public function store(Request $request)
    {
        if ($mensajeLimite = \App\Services\ConfiguracionService::limiteExcedido('sucursales')) {
            return back()->withInput()->with('error', $mensajeLimite);
        }

        $request->validate([
            'suc_nombre' => 'required|string|max:100',
            'suc_direccion' => 'nullable|string',
            'suc_telefono' => 'nullable|string|max:30',
        ]);

        $data = $request->all();
        $data['suc_activa'] = $request->has('suc_activa') ? true : false;

        Sucursal::create($data);
        return redirect()->route('sucursales.index')->with('success', 'Sucursal registrada exitosamente.');
    }

    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::findOrFail($id);

        $request->validate([
            'suc_nombre' => 'required|string|max:100',
            'suc_direccion' => 'nullable|string',
            'suc_telefono' => 'nullable|string|max:30',
        ]);

        $data = $request->all();
        $data['suc_activa'] = $request->has('suc_activa') ? true : false;

        $sucursal->update($data);
        return redirect()->route('sucursales.index')->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy($id)
    {
        Sucursal::findOrFail($id)->delete();
        return redirect()->route('sucursales.index')->with('success', 'Sucursal eliminada del sistema.');
    }
}