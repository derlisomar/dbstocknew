<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Sucursal;

class ControlProductoController extends Controller
{

    public function index(\Illuminate\Http\Request $request)
    {
        $sucursal_id = $request->input('suc_id');

        // Búsquedas independientes
        $buscarGen = $request->input('buscar_gen');
        $buscarOut = $request->input('buscar_out');
        $buscarTop = $request->input('buscar_top');

        // Consultas Base
        $queryGeneral = \App\Models\Producto::with('sucursal');
        $querySinStock = \App\Models\Producto::where('pro_stockactual', '<=', 0)->with('sucursal');
        $queryMasVendidos = \App\Models\Producto::withSum('detalle_ventas as total_vendido', 'det_cantidad')->with('sucursal');

        // Filtro por Sucursal (Global)
        if ($sucursal_id) {
            $queryGeneral->where('suc_id', $sucursal_id);
            $querySinStock->where('suc_id', $sucursal_id);
            $queryMasVendidos->where('suc_id', $sucursal_id);
        }

        // Filtros de Búsqueda Individuales
        if ($buscarGen) $queryGeneral->where('pro_nombre', 'ILIKE', "%{$buscarGen}%");
        if ($buscarOut) $querySinStock->where('pro_nombre', 'ILIKE', "%{$buscarOut}%");
        if ($buscarTop) $queryMasVendidos->where('pro_nombre', 'ILIKE', "%{$buscarTop}%");

        // Paginaciones Nombradas para que no choquen entre sí
        $productosGral = $queryGeneral->orderBy('pro_id', 'desc')->paginate(15, ['*'], 'page_gen')->withQueryString();
        $productosSinStock = $querySinStock->orderBy('pro_stockactual', 'asc')->paginate(15, ['*'], 'page_out')->withQueryString();
        
        // Los más vendidos suelen ser un top fijo (ej. 10), pero puedes paginarlo si deseas
        $productosMasVendidos = $queryMasVendidos->orderByRaw('total_vendido DESC NULLS LAST')->take(10)->get();
        
        $sucursales = \App\Models\Sucursal::where('suc_activa', true)->get();

        return view('operaciones.productos_control', compact(
            'productosGral', 
            'productosSinStock', 
            'productosMasVendidos', 
            'sucursales',
            'sucursal_id',
            'buscarGen', 'buscarOut', 'buscarTop'
        ));
    }

    // Método AJAX para cambiar estados (Activo, Mayorista, Promoción)
    public function toggleEstado(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $campo = $request->campo;
        
        if (in_array($campo, ['pro_activo', 'pro_permite_mayorista', 'pro_en_promocion'])) {
            $producto->$campo = !$producto->$campo;
            $producto->save();
            return response()->json(['success' => true, 'estado' => $producto->$campo]);
        }
        return response()->json(['success' => false], 400);
    }
}