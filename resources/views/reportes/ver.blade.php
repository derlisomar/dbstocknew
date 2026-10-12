@extends('layouts.admin')
@section('contenido')
@php
    use App\Reportes\Exportador;
    $filtros = $def['filtros'];
    $conFechas = in_array('fechas', $filtros, true);
    $q = request()->query();
    $input = 'w-full mt-1 border border-gray-300 dark:border-gray-700 rounded-lg p-2 bg-transparent dark:text-white text-sm';
@endphp
<div class="space-y-5" x-data="{
    desde: '{{ $f['desde'] }}', hasta: '{{ $f['hasta'] }}',
    rango(t){ const h=new Date(), f=d=>d.toISOString().slice(0,10), s=new Date(h);
        if(t==='hoy'){this.desde=this.hasta=f(h)}
        if(t==='ayer'){s.setDate(h.getDate()-1);this.desde=this.hasta=f(s)}
        if(t==='7'){s.setDate(h.getDate()-6);this.desde=f(s);this.hasta=f(h)}
        if(t==='mes'){this.desde=f(new Date(h.getFullYear(),h.getMonth(),1));this.hasta=f(h)}
        if(t==='mespas'){this.desde=f(new Date(h.getFullYear(),h.getMonth()-1,1));this.hasta=f(new Date(h.getFullYear(),h.getMonth(),0))}
        if(t==='anio'){this.desde=f(new Date(h.getFullYear(),0,1));this.hasta=f(h)}
    } }">
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <a href="{{ route('reportes.index') }}" class="text-xs text-blue-600 hover:underline">← Centro de Reportes</a>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $def['nombre'] }}</h2>
            <p class="text-sm text-gray-500 max-w-3xl">{{ $def['desc'] }}</p>
        </div>
        @if($puedeExportar)
        <div class="flex gap-2">
            <a href="{{ route('reportes.xlsx', array_merge(['clave' => $clave], $q)) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold">📗 Descargar Excel</a>
            <a href="{{ route('reportes.pdf', array_merge(['clave' => $clave], $q)) }}" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-bold">📕 Descargar PDF</a>
        </div>
        @endif
    </div>

    <form method="GET" class="bg-white dark:bg-[#1c2434] p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm space-y-3">
        @if($conFechas)
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach(['hoy' => 'Hoy', 'ayer' => 'Ayer', '7' => 'Últimos 7 días', 'mes' => 'Este mes', 'mespas' => 'Mes pasado', 'anio' => 'Este año'] as $k => $t)
                <button type="button" @click="rango('{{ $k }}')" class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-blue-100 hover:text-blue-700">{{ $t }}</button>
            @endforeach
        </div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            @if($conFechas)
            <div><label class="text-xs font-bold text-gray-500 uppercase">Desde</label><input type="date" name="desde" x-model="desde" class="{{ $input }}"></div>
            <div><label class="text-xs font-bold text-gray-500 uppercase">Hasta</label><input type="date" name="hasta" x-model="hasta" class="{{ $input }}"></div>
            @endif
            @if(empty($def['global']))
            <div><label class="text-xs font-bold text-gray-500 uppercase">Sucursal</label>
                <select name="suc_id" class="{{ $input }}">
                    <option value="">{{ $sucursales->count() > 1 ? 'Todas (una hoja por sucursal)' : 'Todas' }}</option>
                    @foreach($sucursales as $s)<option value="{{ $s->suc_id }}" @selected($f['suc_id'] == $s->suc_id)>{{ $s->suc_nombre }}</option>@endforeach
                </select></div>
            @endif
            @if(isset($filtros['agrupar']))
            <div><label class="text-xs font-bold text-gray-500 uppercase">Agrupar</label>
                <select name="agrupar" class="{{ $input }}">@foreach($filtros['agrupar'] as $k => $t)<option value="{{ $k }}" @selected(($f['agrupar'] ?? '') === $k)>{{ $t }}</option>@endforeach</select></div>
            @endif
            @if(isset($filtros['dias']))
            <div><label class="text-xs font-bold text-gray-500 uppercase">{{ $filtros['dias'][0] }}</label><input type="number" min="0" name="dias" value="{{ $f['dias'] }}" class="{{ $input }}"></div>
            @endif
            @if(in_array('usuario', $filtros, true))
            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Usuario</label>
                <select name="usu_id" class="{{ $input }}"><option value="">Todos</option>@foreach($usuarios as $u)<option value="{{ $u->usu_id }}" @selected($f['usu_id'] == $u->usu_id)>{{ trim($u->usu_nombre.' '.$u->usu_apellido) }}</option>@endforeach</select></div>
            @endif
            @if(in_array('producto', $filtros, true))
            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Producto (código o nombre)</label><input type="text" name="producto" value="{{ $f['producto'] }}" placeholder="Todos" class="{{ $input }}"></div>
            @endif
            @if(in_array('cliente', $filtros, true))
            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500 uppercase">Cliente (nombre o RUC/CI)</label><input type="text" name="cliente" value="{{ $f['cliente'] }}" placeholder="Todos" class="{{ $input }}"></div>
            @endif
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm">Ver reporte</button>
        </div>
    </form>

    <p class="text-sm text-gray-500">{{ $doc['subtitulo'] }}</p>

    @foreach($doc['secciones'] as $s)
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-blue-50 dark:bg-blue-900/20 flex justify-between items-center">
            <h3 class="font-bold text-blue-900 dark:text-blue-200">{{ $s['titulo'] }}</h3>
            <span class="text-xs text-gray-500">{{ number_format($s['cantidad'], 0, ',', '.') }} filas</span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase text-xs">
                <tr>@foreach($s['columnas'] as $c)<th class="px-3 py-2 {{ $c['tipo'] !== 'texto' ? 'text-right' : '' }} whitespace-nowrap">{{ $c['t'] }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                @forelse($s['filas'] as $fila)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    @foreach($s['columnas'] as $i => $c)
                    <td class="px-3 py-2 {{ $c['tipo'] !== 'texto' ? 'text-right tabular-nums' : '' }}">{{ Exportador::formato($fila[$i] ?? '', $c['tipo']) }}</td>
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="{{ count($s['columnas']) }}" class="px-3 py-8 text-center text-gray-400">Sin datos para los filtros elegidos.</td></tr>
                @endforelse
            </tbody>
            @if($s['totales'] && $s['cantidad'])
            <tfoot class="bg-blue-50 dark:bg-blue-900/20 font-bold text-blue-900 dark:text-blue-200">
                <tr>@foreach($s['columnas'] as $i => $c)
                    <td class="px-3 py-2 {{ $c['tipo'] !== 'texto' ? 'text-right tabular-nums' : '' }}">{{ isset($s['totales'][$i]) ? Exportador::formato($s['totales'][$i], $c['tipo']) : ($i === 0 ? 'TOTAL' : '') }}</td>
                @endforeach</tr>
            </tfoot>
            @endif
        </table>
        </div>
        @if($s['cantidad'] > $max)
        <div class="px-4 py-2 text-xs text-amber-700 bg-amber-50">Se muestran las primeras {{ $max }} filas. El Excel y el PDF incluyen todas ({{ number_format($s['cantidad'], 0, ',', '.') }}).</div>
        @endif
    </div>
    @endforeach
</div>
@endsection
