<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperacionesClienteController extends Controller
{
public function index(\Illuminate\Http\Request $request)
    {
        // 1. ESTADÍSTICAS GLOBALES (Se calculan sobre toda la base de datos, sin importar la paginación)
        $totalClientes = \App\Models\Cliente::count();
        $clientesBloqueados = \App\Models\Cliente::where('cli_bloqueado', true)->count();
        
        $mejorCliente = \App\Models\Cliente::withSum('ventas as ventas_sum_vta_total', 'vta_total')
            ->orderBy('ventas_sum_vta_total', 'desc')
            ->first();

        // 2. CONSULTA BASE PARA LA TABLA
        $query = \App\Models\Cliente::withSum('ventas as ventas_sum_vta_total', 'vta_total')
            ->withSum('cuentasCobrar as cuentas_cobrar_sum_cred_saldo_pendiente', 'cred_saldo_pendiente');

        // 3. BUSCADOR GLOBAL (Busca en todo el registro)
        if ($request->filled('buscar')) {
            $term = strtolower($request->buscar);
            $query->where(function($q) use ($term) {
                $q->whereRaw('LOWER(cli_nombre) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(cli_apellido) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(cli_ruc_ci) LIKE ?', ["%{$term}%"]);
            });
        }

        // 4. PAGINACIÓN (15 por página) con withQueryString() para no perder la búsqueda al cambiar de página
        $clientes = $query->orderBy('cli_nombre', 'asc')->paginate(15)->withQueryString();

        return view('operaciones.clientes_control', compact('clientes', 'totalClientes', 'clientesBloqueados', 'mejorCliente'));
    }

    public function toggleEstado(Request $request, $id)
    {
        $cliente = Cliente::findOrFail($id);
        $campo = $request->input('campo');

        // Validamos que el campo a modificar sea permitido por seguridad
        $camposPermitidos = ['cli_es_mayorista', 'cli_permitir_credito', 'cli_bloqueado'];
        
        if (in_array($campo, $camposPermitidos)) {
            $cliente->$campo = !$cliente->$campo;
            $cliente->save();

            return response()->json([
                'success' => true, 
                'estado' => $cliente->$campo,
                'message' => 'Estado actualizado correctamente.'
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Campo no válido.'], 400);
    }
}