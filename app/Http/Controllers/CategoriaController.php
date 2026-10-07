<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('cat_id', 'asc')->get();
        return view('categorias.index', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cat_nombre' => 'required|string|max:100|unique:categorias,cat_nombre',
            'cat_descripcion' => 'nullable|string',
        ]);

        Categoria::create($request->all());
        return redirect()->route('categorias.index')->with('success', 'Categoría registrada correctamente.');
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'cat_nombre' => 'required|string|max:100|unique:categorias,cat_nombre,' . $id . ',cat_id',
            'cat_descripcion' => 'nullable|string',
        ]);

        $categoria->update($request->all());
        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy($id)
    {
        Categoria::findOrFail($id)->delete();
        return redirect()->route('categorias.index')->with('success', 'Categoría eliminada correctamente.');
    }
}