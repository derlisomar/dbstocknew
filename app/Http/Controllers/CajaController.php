<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function index()
    {
        $cajas = Caja::with('sucursal')->orderBy('caj_id', 'asc')->get();
        $sucursales = Sucursal::where('suc_activa', true)->get();
        
        return view('cajas.index', compact('cajas', 'sucursales'));
    }

  public function store(Request $request)
{
    $request->validate([
        'suc_id' => 'required|exists:sucursales,suc_id',
        'caj_nombre' => 'required|string|max:50',
        'caj_impresora' => 'nullable|string|max:100',
        'caj_tipo_impresion' => 'nullable|string|max:30', // 👇 NUEVA LÍNEA DE VALIDACIÓN
    ]);

    Caja::create([
        'suc_id' => $request->suc_id,
        'caj_nombre' => $request->caj_nombre,
        'caj_impresora' => $request->caj_impresora,
        'caj_tipo_impresion' => $request->caj_tipo_impresion, // 👇 NUEVA LÍNEA PARA GUARDAR
        'caj_activa' => $request->has('caj_activa') ? true : false,
    ]);

    return redirect()->route('cajas.index')->with('success', 'Caja registrada correctamente.');
}

public function update(Request $request, $id)
{
    $caja = Caja::findOrFail($id);

    $request->validate([
        'suc_id' => 'required|exists:sucursales,suc_id',
        'caj_nombre' => 'required|string|max:50',
        'caj_impresora' => 'nullable|string|max:100',
        'caj_tipo_impresion' => 'nullable|string|max:30', // 👇 NUEVA LÍNEA DE VALIDACIÓN
    ]);

    $caja->update([
        'suc_id' => $request->suc_id,
        'caj_nombre' => $request->caj_nombre,
        'caj_impresora' => $request->caj_impresora,
        'caj_tipo_impresion' => $request->caj_tipo_impresion, // 👇 NUEVA LÍNEA PARA ACTUALIZAR
        'caj_activa' => $request->has('caj_activa') ? true : false,
    ]);

    return redirect()->route('cajas.index')->with('success', 'Caja actualizada correctamente.');
}
}