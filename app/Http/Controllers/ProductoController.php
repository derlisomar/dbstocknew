<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Deposito;
use Illuminate\Http\Request;

class ProductoController extends Controller
{

    public function index()
    {
        // 1. Calcular las estadísticas para las tarjetas superiores
        $totalProductos = \App\Models\Producto::count();
        $sinStock = \App\Models\Producto::where('pro_stockactual', '<=', 0)->count();
        $inactivos = \App\Models\Producto::where('pro_activo', false)->count();

        // 2. Obtener los productos de 30 en 30 usando paginate() en lugar de get()
        $productos = \App\Models\Producto::with(['categoria', 'proveedor', 'sucursal', 'deposito'])
                        ->orderBy('pro_id', 'desc')
                        ->paginate(30);

        $categorias = \App\Models\Categoria::all();
        $proveedores = \App\Models\Proveedor::all();
        $sucursales = \App\Models\Sucursal::all();
        $depositos = \App\Models\Deposito::all();

        return view('productos.index', compact(
            'productos', 
            'categorias', 
            'proveedores', 
            'sucursales', 
            'depositos',
            'totalProductos',
            'sinStock',
            'inactivos'
        ));
    }

 public function store(Request $request)
    {
        // ... (tus validaciones anteriores) ...
        $data = $request->all();
        $data['pro_activo'] = $request->has('pro_activo');

        if ($request->hasFile('pro_imagen')) {
            $data['pro_imagen'] = $request->file('pro_imagen')->store('productos', 'public');
        }

        Producto::create($data);
        return redirect()->route('productos.index')->with('success', 'Producto registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        // ... (tus validaciones anteriores) ...
        $data = $request->all();
        $data['pro_activo'] = $request->has('pro_activo');

        if ($request->hasFile('pro_imagen')) {
            $data['pro_imagen'] = $request->file('pro_imagen')->store('productos', 'public');
        }

        $producto->update($data);
        return redirect()->route('productos.index')->with('success', 'Producto actualizado correctamente.');
    }



    public function destroy($id)
    {
        Producto::findOrFail($id)->delete();
        return redirect()->route('productos.index')->with('success', 'Producto eliminado correctamente.');
    }
}