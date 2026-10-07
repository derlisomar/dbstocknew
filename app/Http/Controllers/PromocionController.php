<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Categoria;

class PromocionController extends Controller
{
    public function index()
    {
        $promociones = Promocion::with(['producto', 'categoria'])->orderBy('prom_id', 'desc')->get();
        $categorias = Categoria::all();
        $productos = Producto::where('pro_activo', true)->get();

        // Calcular KPIs
        $hoy = now()->toDateString();
        $promosActivas = $promociones->where('prom_activa', true)->where('prom_fecha_fin', '>=', $hoy);
        
        $totalPromos = $promosActivas->count();
        $productosAfectados = 0;

        foreach($promosActivas as $promo) {
            if($promo->prom_aplica_a == 'PRODUCTO') {
                $productosAfectados += 1;
            } elseif($promo->prom_aplica_a == 'CATEGORIA') {
                $productosAfectados += Producto::where('cat_id', $promo->cat_id)->where('pro_activo', true)->count();
            }
        }

        return view('operaciones.promociones', compact('promociones', 'categorias', 'productos', 'totalPromos', 'productosAfectados', 'hoy'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'prom_nombre' => 'required|string|max:150',
            'prom_tipo_descuento' => 'required|in:PORCENTAJE,MONTO',
            'prom_valor' => 'required|numeric|min:1',
            'prom_fecha_fin' => 'required|date',
            'prom_aplica_a' => 'required|in:PRODUCTO,CATEGORIA',
            'cat_id' => 'nullable|required_if:prom_aplica_a,CATEGORIA|exists:categorias,cat_id',
            'pro_id' => 'nullable|required_if:prom_aplica_a,PRODUCTO|exists:productos,pro_id',
        ]);

        $data['prom_fecha_inicio'] = now()->toDateString();
        $data['prom_activa'] = true;

        Promocion::create($data);
        return redirect()->route('promociones.index')->with('success', 'Promoción activada exitosamente.');
    }

    public function toggleEstado($id)
    {
        $promocion = Promocion::findOrFail($id);
        $promocion->prom_activa = !$promocion->prom_activa;
        $promocion->save();
        return redirect()->route('promociones.index')->with('success', 'Estado de promoción actualizado.');
    }
    public function update(Request $request, $id)
    {
        $promocion = Promocion::findOrFail($id);
        $data = $request->validate([
            'prom_nombre' => 'required|string|max:150',
            'prom_tipo_descuento' => 'required|in:PORCENTAJE,MONTO',
            'prom_valor' => 'required|numeric|min:1',
            'prom_fecha_fin' => 'required|date',
            'prom_aplica_a' => 'required|in:PRODUCTO,CATEGORIA',
            'cat_id' => 'nullable|required_if:prom_aplica_a,CATEGORIA',
            'pro_id' => 'nullable|required_if:prom_aplica_a,PRODUCTO',
        ]);

        $promocion->update($data);
        return redirect()->route('promociones.index')->with('success', 'Promoción actualizada correctamente.');
    }

    public function destroy($id)
    {
        Promocion::findOrFail($id)->delete();
        return redirect()->route('promociones.index')->with('success', 'Promoción eliminada.');
    }

}