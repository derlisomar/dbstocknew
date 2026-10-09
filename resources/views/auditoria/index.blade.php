@extends('layouts.admin')

@section('contenido')
<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🕵️ Registro de Auditoría</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Quién hizo qué y cuándo: anulaciones, devoluciones, cobros, cierres de caja y cambios de usuarios. Solo lectura.</p>
    </div>

    <form method="GET" action="{{ route('auditoria.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Usuario</label>
            <select name="usuario" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
                <option value="">Todos</option>
                @foreach($todosUsuarios as $u)
                    <option value="{{ $u->usu_id }}" @selected((string) request('usuario') === (string) $u->usu_id)>{{ $u->usu_usuario }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Acción</label>
            <select name="accion" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
                <option value="">Todas</option>
                @foreach($acciones as $a)
                    <option value="{{ $a }}" @selected(request('accion') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Buscar (nro. o detalle)</label>
            <input type="text" name="q" value="{{ request('q') }}" maxlength="60" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div class="flex items-end gap-1">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition">Filtrar</button>
            @if(request()->query())
                <a href="{{ route('auditoria.index') }}" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg text-sm font-bold transition">✖</a>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-3 px-4">Fecha</th>
                        <th class="py-3 px-4">Usuario</th>
                        <th class="py-3 px-4">Acción</th>
                        <th class="py-3 px-4">Registro</th>
                        <th class="py-3 px-4">Detalle</th>
                        <th class="py-3 px-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($registros as $r)
                        @php
                            $u = $usuarios->get($r->usu_id);
                            $detalle = json_decode((string) $r->aud_detalle, true);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition align-top">
                            <td class="py-3 px-4 whitespace-nowrap text-xs">{{ \Carbon\Carbon::parse($r->aud_fecha)->format('d/m/Y H:i:s') }}</td>
                            <td class="py-3 px-4">{{ $u->usu_usuario ?? ($r->usu_id ? '#'.$r->usu_id : 'Sistema') }}</td>
                            <td class="py-3 px-4"><span class="px-2 py-1 text-[11px] font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 rounded">{{ $r->aud_accion }}</span></td>
                            <td class="py-3 px-4 text-xs">{{ $r->aud_tabla }} {{ $r->aud_registro_id ? '#'.$r->aud_registro_id : '' }}</td>
                            <td class="py-3 px-4 text-xs">
                                @if(is_array($detalle))
                                    @foreach($detalle as $clave => $valor)
                                        <div><span class="text-gray-500">{{ $clave }}:</span> {{ is_scalar($valor) || $valor === null ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE) }}</div>
                                    @endforeach
                                @else
                                    {{ $r->aud_detalle }}
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $r->aud_ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">No hay registros con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $registros->links() }}</div>
    </div>
</div>
@endsection
