@extends('vendedor.layout')
@section('titulo', 'Resumen')
@section('contenido')
@php
    $pct = $total ? round($hechos * 100 / $total) : 0;
    $clases = ['SIN_LICENCIA' => ['b-mu', 'Sin licencia'], 'ACTIVA' => ['b-ok', 'Al día'], 'POR_VENCER' => ['b-warn', 'Por vencer'], 'GRACIA' => ['b-warn', 'En gracia'], 'SOLO_LECTURA' => ['b-bad', 'Solo lectura']];
    [$cl, $tx] = $clases[$licencia['estado']];
@endphp
<h1>{{ \App\Services\ConfiguracionService::get('negocio_nombre') ?: 'Negocio sin nombre' }}</h1>
<p class="pv-sub">Edición {{ config('modulos.ediciones')[$edicion] }} · Licencia <span class="badge {{ $cl }}">{{ $tx }}</span>
    @if($licencia['vence'] && $licencia['tipo'] !== 'PERPETUA') · vence el {{ \Carbon\Carbon::parse($licencia['vence'])->format('d/m/Y') }}@endif</p>

<div class="pv-card">
    <h2>Lista de preparación de este negocio: {{ $hechos }} de {{ $total }}</h2>
    <div class="bar" style="margin-bottom:8px"><i style="width:{{ $pct }}%"></i></div>
    @foreach($lista as $item)
        <div class="chk">
            <span class="dot {{ $item['ok'] ? 'ok' : '' }}">{{ $item['ok'] ? '✓' : '' }}</span>
            <div style="flex:1"><b>{{ $item['titulo'] }}</b><div class="mu">{{ $item['detalle'] }}</div></div>
            <a class="btn g sm" href="{{ $item['enlace'] }}">{{ $item['ok'] ? 'Ver' : 'Completar' }}</a>
        </div>
    @endforeach
</div>
@endsection
