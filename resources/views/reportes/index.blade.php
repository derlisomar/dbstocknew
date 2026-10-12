@extends('layouts.admin')
@section('contenido')
<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">📑 Centro de Reportes</h2>
        <p class="text-sm text-gray-500">Elegí un informe, filtrá por fechas y sucursal, y descargalo en Excel o PDF. Si tenés varias sucursales, cada una sale en su propia hoja / sección.</p>
    </div>

    @if($tablero && $tablero['tarjetas'])
    @php
        $tonos = ['azul' => 'border-l-blue-500', 'verde' => 'border-l-emerald-500', 'ambar' => 'border-l-amber-500', 'rojo' => 'border-l-red-500'];
        $valor = ['azul' => 'text-gray-900 dark:text-white', 'verde' => 'text-emerald-600', 'ambar' => 'text-amber-600', 'rojo' => 'text-red-600'];
        $max = $tablero['serie'] ? max(1, max(array_column($tablero['serie'], 'total'))) : 1;
    @endphp
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Tablero</h3>
                <p class="text-xs text-gray-500 capitalize">{{ $tablero['hoy'] }}</p>
            </div>
            @if($sucursales->count() > 1)
            <form method="GET" class="flex items-center gap-2 text-sm">
                <label class="text-xs font-bold text-gray-500 uppercase">Sucursal</label>
                <select name="suc_id" onchange="this.form.submit()" class="border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white">
                    <option value="">Todas</option>
                    @foreach($sucursales as $s)<option value="{{ $s->suc_id }}" @selected($sucId == $s->suc_id)>{{ $s->suc_nombre }}</option>@endforeach
                </select>
            </form>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($tablero['tarjetas'] as $c)
            <a href="{{ $c['url'] }}" class="block bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 border-l-4 {{ $tonos[$c['tono']] }} rounded-xl p-4 shadow-sm hover:shadow-md transition">
                <div class="text-xs font-bold uppercase text-gray-500">{{ $c['titulo'] }}</div>
                <div class="text-2xl font-black mt-1 {{ $valor[$c['tono']] }}">{{ $c['valor'] }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $c['sub'] }}</div>
            </a>
            @endforeach
        </div>

        @if($tablero['serie'])
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="text-sm font-bold text-gray-800 dark:text-white mb-3">Ventas de los últimos 14 días</div>
                <div class="flex items-end gap-1.5 h-40">
                    @foreach($tablero['serie'] as $d)
                    <div class="flex-1 flex flex-col items-center justify-end h-full group" title="{{ $d['sem'] }} {{ $d['dia'] }}: Gs. {{ number_format($d['total'], 0, ',', '.') }}">
                        <div class="w-full rounded-t bg-blue-500 group-hover:bg-blue-600 transition" style="height: {{ max(2, round($d['total'] / $max * 100)) }}%"></div>
                        <div class="text-[10px] text-gray-400 mt-1">{{ $d['dia'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="text-sm font-bold text-gray-800 dark:text-white mb-3">Más vendidos del mes</div>
                @forelse($tablero['top'] as $i => $t)
                <div class="flex justify-between gap-3 py-1.5 text-sm {{ $i ? 'border-t border-gray-100 dark:border-gray-800' : '' }}">
                    <span class="truncate text-gray-700 dark:text-gray-200">{{ $i + 1 }}. {{ $t['nombre'] }}</span>
                    <span class="font-semibold whitespace-nowrap tabular-nums">Gs. {{ number_format($t['total'], 0, ',', '.') }}</span>
                </div>
                @empty
                <p class="text-sm text-gray-400">Todavía no hay ventas este mes.</p>
                @endforelse
            </div>
        </div>
        @endif
    </section>
    @endif

    @php($colores = ['blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300', 'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300', 'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300', 'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300', 'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-300', 'cyan' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/20 dark:text-cyan-300'])

    @forelse($grupos as $g)
    <section>
        <div class="flex items-center gap-3 mb-3">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center text-xl {{ $colores[$g['color']] }}">{{ $g['icono'] }}</span>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">{{ $g['nombre'] }}</h3>
            <span class="text-xs text-gray-400">{{ count($g['reportes']) }} reportes</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($g['reportes'] as $clave => $r)
            <a href="{{ route('reportes.ver', $clave) }}" class="group bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm hover:border-blue-400 hover:shadow-md transition">
                <div class="font-bold text-gray-800 dark:text-white group-hover:text-blue-600">{{ $r['nombre'] }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ $r['desc'] }}</p>
            </a>
            @endforeach
        </div>
    </section>
    @empty
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-sm">Tu rol todavía no tiene reportes asignados. Pedile al administrador que te habilite desde "Gestión de Roles".</div>
    @endforelse
</div>
@endsection
