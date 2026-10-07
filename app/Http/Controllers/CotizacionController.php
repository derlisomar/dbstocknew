<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use Illuminate\Http\Request;

class CotizacionController extends Controller
{
    public function index()
    {
        $cotizacionActiva = Cotizacion::where('cot_activa', true)->first();
        $historial = Cotizacion::orderBy('cot_fecha', 'desc')->orderBy('cot_id', 'desc')->get();
        
        return view('cotizaciones.index', compact('cotizacionActiva', 'historial'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cot_dolar' => 'required|numeric|min:1',
            'cot_real' => 'required|numeric|min:1',
        ]);

        // Desactivar todas las cotizaciones anteriores
        Cotizacion::where('cot_activa', true)->update(['cot_activa' => false]);

        // Crear la nueva cotización como activa
        Cotizacion::create([
            'cot_fecha' => now()->toDateString(),
            'cot_dolar' => $request->cot_dolar,
            'cot_real' => $request->cot_real,
            'cot_activa' => true
        ]);

        return redirect()->route('cotizaciones.index')->with('success', 'Cotización actualizada correctamente. El PDV ya utiliza estos valores.');
    }
}