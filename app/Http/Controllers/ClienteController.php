<?php
namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        // 1. Calcular estadísticas para las tarjetas superiores
        $totalClientes = \App\Models\Cliente::count();
        $clientesCredito = \App\Models\Cliente::where('cli_limite_credito', '>', 0)->count();
        $clientesMayoristas = \App\Models\Cliente::where('cli_es_mayorista', true)->count();

        // 2. Obtener los clientes de 15 en 15 ordenados por los más recientes
        $clientes = \App\Models\Cliente::orderBy('cli_id', 'desc')->paginate(15);

        return view('clientes.index', compact('clientes', 'totalClientes', 'clientesCredito', 'clientesMayoristas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cli_ruc_ci' => 'required|string|max:30|unique:clientes,cli_ruc_ci',
            'cli_nombre' => 'required|string|max:100',
            'cli_apellido' => 'nullable|string|max:100',
            'cli_telefono' => 'nullable|string|max:30',
            'cli_email' => 'nullable|email|max:100',
            'cli_direccion' => 'nullable|string',
            'cli_limite_credito' => 'nullable|numeric|min:0',
        ]);

        Cliente::create($request->all());
        return redirect()->route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);

        $request->validate([
            'cli_ruc_ci' => 'required|string|max:30|unique:clientes,cli_ruc_ci,' . $id . ',cli_id',
            'cli_nombre' => 'required|string|max:100',
            'cli_apellido' => 'nullable|string|max:100',
            'cli_telefono' => 'nullable|string|max:30',
            'cli_email' => 'nullable|email|max:100',
            'cli_direccion' => 'nullable|string',
            'cli_limite_credito' => 'nullable|numeric|min:0',
        ]);

        $cliente->update($request->all());
        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy($id)
    {
        Cliente::findOrFail($id)->delete();
        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }
}