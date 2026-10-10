@extends('vendedor.layout')
@section('titulo', 'Resumen')
@section('contenido')
@php
    $pct = $total ? round($hechos * 100 / $total) : 0;
    $clases = ['SIN_LICENCIA' => ['b-mu', 'Sin licencia'], 'ACTIVA' => ['b-ok', 'Al día'], 'POR_VENCER' => ['b-warn', 'Por vencer'], 'GRACIA' => ['b-warn', 'En gracia'], 'SOLO_LECTURA' => ['b-bad', 'Solo lectura']];
    [$cl, $tx] = $clases[$licencia['estado']];
    $peor = \App\Services\SaludService::peor($salud);
    $n = fn ($v) => $v === null ? '-' : number_format($v, 0, ',', '.');
@endphp
<div class="pv-head">
    <div>
        <h1>{{ \App\Services\ConfiguracionService::get('negocio_nombre') ?: 'Negocio sin nombre' }}</h1>
        <p class="pv-sub">Edición {{ config('modulos.ediciones')[$edicion] }} · Licencia <span class="badge {{ $cl }}">{{ $tx }}</span>
            @if($licencia['vence'] && $licencia['tipo'] !== 'PERPETUA') · vence el {{ \Carbon\Carbon::parse($licencia['vence'])->format('d/m/Y') }}@endif</p>
    </div>
    <div class="acciones">
        <a class="btn g" href="{{ route('vendedor.licencia') }}">Licencia y pagos</a>
        <a class="btn" href="{{ route('vendedor.planes') }}">Planes y precios</a>
    </div>
</div>

<div class="pv-stats">
    <div class="stat ac"><div class="n">Gs. {{ $n($cifras['total_mes']) }}</div><div class="t">Ventas del mes</div></div>
    <div class="stat"><div class="n">{{ $n($cifras['ventas_mes']) }}</div><div class="t">Operaciones del mes</div></div>
    <div class="stat"><div class="n">{{ $n($cifras['usuarios']) }}</div><div class="t">Usuarios activos</div></div>
    <div class="stat"><div class="n">{{ $n($cifras['sucursales']) }} / {{ $n($cifras['cajas']) }}</div><div class="t">Sucursales / cajas</div></div>
    <div class="stat"><div class="n">{{ $n($cifras['productos']) }}</div><div class="t">Productos</div></div>
</div>

<div class="pv-2">
    <div class="pv-card">
        <h2>Preparación del negocio <span class="badge b-info">{{ $hechos }} de {{ $total }}</span></h2>
        <div class="bar" style="margin-bottom:6px"><i style="width:{{ $pct }}%"></i></div>
        @foreach($lista as $item)
            <div class="chk">
                <span class="dot {{ $item['ok'] ? 'ok' : '' }}">{{ $item['ok'] ? '✓' : '' }}</span>
                <div style="flex:1"><b>{{ $item['titulo'] }}</b><div class="mu">{{ $item['detalle'] }}</div></div>
                <a class="btn g sm" href="{{ $item['enlace'] }}">{{ $item['ok'] ? 'Ver' : 'Completar' }}</a>
            </div>
        @endforeach
    </div>

    <div class="pv-card">
        <h2>Salud del sistema <span class="badge {{ ['ok' => 'b-ok', 'warn' => 'b-warn', 'bad' => 'b-bad'][$peor] }}">{{ ['ok' => 'Todo bien', 'warn' => 'Revisar', 'bad' => 'Atención'][$peor] }}</span></h2>
        @foreach($salud as $s)
            <div class="chk">
                <span class="dot {{ $s['nivel'] }}">{{ $s['nivel'] === 'ok' ? '✓' : '!' }}</span>
                <div style="flex:1"><b>{{ $s['titulo'] }}</b><div class="mu">{{ $s['detalle'] }}</div></div>
            </div>
        @endforeach
    </div>
</div>
@endsection
