<?php

namespace App\Http\Controllers;

use App\Models\Deposito;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class DepositoController extends Controller
{
    public function index()
    {
        $depositos = Deposito::with('sucursal')->orderBy('dep_id', 'asc')->get();
        $sucursales = Sucursal::all();
        return view('depositos.index', compact('depositos', 'sucursales'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'suc_id' => 'required|exists:sucursales,suc_id',
            'dep_nombre' => 'required|string|max:100',
            'dep_descripcion' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['dep_activo'] = $request->has('dep_activo') ? true : false;

        Deposito::create($data);
        return redirect()->route('depositos.index')->with('success', 'Depósito registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $deposito = Deposito::findOrFail($id);

        $request->validate([
            'suc_id' => 'required|exists:sucursales,suc_id',
            'dep_nombre' => 'required|string|max:100',
            'dep_descripcion' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['dep_activo'] = $request->has('dep_activo') ? true : false;

        $deposito->update($data);
        return redirect()->route('depositos.index')->with('success', 'Depósito actualizado correctamente.');
    }

    public function destroy($id)
    {
        Deposito::findOrFail($id)->delete();
        return redirect()->route('depositos.index')->with('success', 'Depósito eliminado correctamente.');
    }
}