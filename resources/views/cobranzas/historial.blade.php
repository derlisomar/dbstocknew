@extends('layouts.admin')

@section('contenido')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🧾 Historial de Cobros</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cobros a crédito registrados. Si uno se cargó por error, se puede anular y la deuda vuelve al cliente.</p>
        </div>
        <a href="{{ route('cobranzas.index') }}" class="text-sm font-bold text-blue-600 hover:underline">← Volver a Cobranzas</a>
    </div>

    <form method="GET" action="{{ route('cobranzas.historial') }}" class="flex flex-wrap items-end gap-3 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Estado</label>
            <select name="estado" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
                <option value="">Todos</option>
                <option value="ACTIVA" @selected(request('estado') === 'ACTIVA')>Vigentes</option>
                <option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anulados</option>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition">Filtrar</button>
        @if(request()->query())
            <a href="{{ route('cobranzas.historial') }}" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg text-sm font-bold transition">✖</a>
        @endif
    </form>

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-3 px-4">Cobro</th>
                        <th class="py-3 px-4">Cliente</th>
                        <th class="py-3 px-4">Cobrado por / Caja</th>
                        <th class="py-3 px-4 text-right">Monto</th>
                        <th class="py-3 px-4">Forma</th>
                        <th class="py-3 px-4 text-center">Estado</th>
                        <th class="py-3 px-4">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($cobros as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition align-top">
                            <td class="py-3 px-4">
                                <span class="font-bold text-gray-800 dark:text-white">#{{ $c->cob_id }}</span><br>
                                <span class="text-xs text-gray-500">{{ $c->cob_fecha ? \Carbon\Carbon::parse($c->cob_fecha)->format('d/m/Y H:i') : '' }}</span>
                            </td>
                            <td class="py-3 px-4">{{ trim(($c->cliente->cli_nombre ?? '').' '.($c->cliente->cli_apellido ?? '')) ?: 'N/A' }}</td>
                            <td class="py-3 px-4 text-xs">{{ $c->usuario->usu_usuario ?? 'N/A' }}<br><span class="text-gray-500">{{ $c->sesion->caja->caj_nombre ?? '' }}</span></td>
                            <td class="py-3 px-4 text-right font-bold">Gs. {{ number_format($c->cob_monto_total, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-xs">{{ $c->cob_formapago ?: 'EFECTIVO' }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($c->cob_estado === 'ANULADA')
                                    <span class="px-2 py-1 text-[11px] font-bold bg-red-100 text-red-700 rounded">Anulado</span>
                                    @if($c->cob_motivo_anulacion)
                                        <div class="text-[10px] text-gray-500 mt-1 max-w-[180px]">{{ $c->cob_motivo_anulacion }}</div>
                                    @endif
                                @else
                                    <span class="px-2 py-1 text-[11px] font-bold bg-emerald-100 text-emerald-700 rounded">Vigente</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('cobranzas.ticket', $c->cob_id) }}" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">Ticket</a>
                                @if($c->cob_estado === 'ACTIVA')
                                    @can('COBRANZAS_ANULAR')
                                    <form method="POST" action="{{ route('cobranzas.anular', $c->cob_id) }}" class="mt-2 flex gap-1"
                                          onsubmit="return confirm('¿Anular el cobro #{{ $c->cob_id }}? La deuda vuelve al cliente y el dinero sale de la caja.');">
                                        @csrf
                                        <input type="text" name="motivo" required maxlength="255" placeholder="Motivo"
                                               class="rounded bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-1 px-2 text-xs w-36 text-gray-800 dark:text-white">
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-bold">Anular</button>
                                    </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-500">No hay cobros con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $cobros->links() }}</div>
    </div>
</div>
@endsection
