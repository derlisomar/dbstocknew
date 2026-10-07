<?php

namespace App\Controllers; // Asegúrate de mantener el namespace App\Http\Controllers según tu estructura
namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::orderBy('prov_id', 'asc')->get();
        return view('proveedores.index', compact('proveedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'prov_razonsocial' => 'required|string|max:150',
            'prov_ruc' => 'nullable|string|max:30|unique:proveedores,prov_ruc',
            'prov_telefono' => 'nullable|string|max:30',
            'prov_email' => 'nullable|email|max:100',
            'prov_direccion' => 'nullable|string',
        ]);

        Proveedor::create($request->all());
        return redirect()->route('proveedores.index')->with('success', 'Proveedor registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $request->validate([
            'prov_razonsocial' => 'required|string|max:150',
            'prov_ruc' => 'nullable|string|max:30|unique:proveedores,prov_ruc,' . $id . ',prov_id',
            'prov_telefono' => 'nullable|string|max:30',
            'prov_email' => 'nullable|email|max:100',
            'prov_direccion' => 'nullable|string',
        ]);

        $proveedor->update($request->all());
        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy($id)
    {
        Proveedor::findOrFail($id)->delete();
        return redirect()->route('proveedores.index')->with('success', 'Proveedor eliminado correctamente.');
    }
}