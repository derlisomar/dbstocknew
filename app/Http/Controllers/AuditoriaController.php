<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pantalla de solo lectura: quién hizo qué y cuándo (anulaciones, devoluciones, cierres, cobros, usuarios...).
 */
class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $consulta = Auditoria::query()->orderByDesc('aud_fecha')->orderByDesc('aud_id');

        if ($request->filled('desde')) {
            $consulta->whereDate('aud_fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $consulta->whereDate('aud_fecha', '<=', $request->hasta);
        }
        if ($request->filled('usuario')) {
            $consulta->where('usu_id', (int) $request->usuario);
        }
        if ($request->filled('accion')) {
            $consulta->where('aud_accion', $request->accion);
        }
        if ($request->filled('q')) {
            // El texto libre busca en el número de registro y en el detalle (sin tratar % y _ como comodines).
            $texto = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($request->q));
            $consulta->where(function ($w) use ($texto) {
                $w->where('aud_registro_id', 'like', $texto.'%')
                  ->orWhere('aud_detalle', 'like', '%'.$texto.'%');
            });
        }

        $registros = $consulta->paginate(30)->withQueryString();

        // Nombres de usuario de la página actual, en una sola consulta.
        $usuarios = User::whereIn('usu_id', $registros->pluck('usu_id')->filter()->unique())
            ->get(['usu_id', 'usu_usuario', 'usu_nombre', 'usu_apellido'])
            ->keyBy('usu_id');

        $acciones = Auditoria::select('aud_accion')->distinct()->orderBy('aud_accion')->pluck('aud_accion');
        $todosUsuarios = User::orderBy('usu_usuario')->get(['usu_id', 'usu_usuario']);

        return view('auditoria.index', compact('registros', 'usuarios', 'acciones', 'todosUsuarios'));
    }
}
