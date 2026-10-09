@extends('layouts.admin')

@section('contenido')
@php
    $etiquetas = [
        'SALDO_INICIAL' => 'Saldo inicial', 'CARGA_INICIAL' => 'Carga inicial', 'VENTA' => 'Venta',
        'ANULACION_VENTA' => 'Anulación de venta', 'DEVOLUCION' => 'Devolución',
        'INGRESO_MERCADERIA' => 'Ingreso de mercadería', 'AJUSTE_ENTRADA' => 'Ajuste (entrada)',
        'AJUSTE_SALIDA' => 'Ajuste (salida)', 'CONTEO' => 'Conteo físico',
        'COMPRA' => 'Compra a proveedor', 'ANULACION_COMPRA' => 'Anulación de compra', 'DEVOLUCION_PROVEEDOR' => 'Devolución a proveedor',
    ];
    $campo = 'rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white';
@endphp
<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🗃️ Inventario y Stock</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cada entrada o salida de mercadería queda registrada con quién la hizo y por qué.</p>
    </div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
@endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if($descuadres->count() > 0)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-bold">Atención: {{ $descuadres->count() }} producto(s) con stock que no coincide con su historial.</p>
            <p class="text-xs mt-1">Alguien cambió el stock directamente en la base de datos. Corregilo con un conteo físico.</p>
            <ul class="mt-2 text-xs list-disc pl-5">
                @foreach($descuadres->take(10) as $d)
                    <li>{{ $d->pro_nombre }}: stock {{ (float) $d->pro_stockactual }}, historial {{ (float) $d->suma_movimientos }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($puedeAjustar)
    <form method="POST" action="{{ route('inventario.registrar') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        @csrf
        <div class="flex flex-col md:col-span-2">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Producto</label>
            <select name="pro_id" required class="{{ $campo }}">
                <option value="">Elegí un producto</option>
                @foreach($productos as $p)
                    <option value="{{ $p->pro_id }}" @selected((string) old('pro_id') === (string) $p->pro_id)>{{ $p->pro_codigo }} - {{ $p->pro_nombre }} (stock {{ (float) $p->pro_stockactual }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Tipo</label>
            <select name="tipo" required class="{{ $campo }}">
                @foreach($tiposManuales as $t)
                    <option value="{{ $t }}" @selected(old('tipo') === $t)>{{ $etiquetas[$t] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Cantidad (en conteo: lo contado)</label>
            <input type="number" name="cantidad" step="0.01" min="0" required value="{{ old('cantidad') }}" class="{{ $campo }}">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Motivo</label>
            <input type="text" name="motivo" maxlength="200" required value="{{ old('motivo') }}" placeholder="Ej: factura 001-001-123" class="{{ $campo }}">
        </div>
        <div class="md:col-span-5 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition">Registrar movimiento</button>
        </div>
    </form>
    @endif

    <form method="GET" action="{{ route('inventario.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Producto</label>
            <select name="producto" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach($productos as $p)
                    <option value="{{ $p->pro_id }}" @selected((string) request('producto') === (string) $p->pro_id)>{{ $p->pro_nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Tipo</label>
            <select name="tipo" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach($tipos as $t)
                    <option value="{{ $t }}" @selected(request('tipo') === $t)>{{ $etiquetas[$t] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Usuario</label>
            <select name="usuario" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->usu_id }}" @selected((string) request('usuario') === (string) $u->usu_id)>{{ $u->usu_usuario }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="{{ $campo }}">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] uppercase font-bold text-gray-500 mb-0.5">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $campo }}">
        </div>
        <div class="flex items-end gap-1">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition">Filtrar</button>
            @if(request()->query())
                <a href="{{ route('inventario.index') }}" class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-3 py-2 rounded-lg text-sm font-bold">✖</a>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-3 px-4">Fecha</th>
                        <th class="py-3 px-4">Producto</th>
                        <th class="py-3 px-4">Tipo</th>
                        <th class="py-3 px-4 text-right">Cantidad</th>
                        <th class="py-3 px-4 text-right">Stock resultante</th>
                        <th class="py-3 px-4">Usuario</th>
                        <th class="py-3 px-4">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse($movimientos as $m)
                        <tr class="mov-fila hover:bg-gray-50 dark:hover:bg-gray-800/50 transition align-top">
                            <td class="py-3 px-4 whitespace-nowrap text-xs">{{ \Carbon\Carbon::parse($m->smo_fecha)->format('d/m/Y H:i') }}</td>
                            <td class="py-3 px-4">{{ $m->producto->pro_nombre ?? '#'.$m->pro_id }}</td>
                            <td class="py-3 px-4 text-xs">{{ $etiquetas[$m->smo_tipo] ?? $m->smo_tipo }}</td>
                            <td class="py-3 px-4 text-right font-bold {{ $m->smo_cantidad < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $m->smo_cantidad > 0 ? '+' : '' }}{{ (float) $m->smo_cantidad }}</td>
                            <td class="py-3 px-4 text-right">{{ (float) $m->smo_stock_resultante }}</td>
                            <td class="py-3 px-4 text-xs">{{ $m->usuario->usu_usuario ?? 'Sistema' }}</td>
                            <td class="py-3 px-4 text-xs">{{ $m->smo_motivo }} @if($m->smo_referencia)<span class="text-gray-400">({{ $m->smo_referencia }})</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-gray-500">No hay movimientos con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $movimientos->links() }}</div>
    </div>
</div>
@endsection
