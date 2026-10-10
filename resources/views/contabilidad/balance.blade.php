@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Balance general</h2><p class="ct-sub">Qué tiene el negocio (activo), qué debe (pasivo) y qué es de los dueños (patrimonio) a una fecha.</p></div></div>
    @include('contabilidad._menu')
    <form method="GET" class="ct-card" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <div><label class="ct-label">Al día</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div>
        <button class="ct-btn" type="submit">Ver</button></form>

    <div class="ct-quad">
        <div class="ct-card">
            <h3>Activo</h3>
            <table class="ct-table">
                @foreach($b['activo'] as $f)<tr><td>{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->importe) }}</td></tr>@endforeach
                <tr class="ct-sub"><td>TOTAL ACTIVO</td><td class="ct-num">{{ $fmt($b['total_activo']) }}</td></tr>
            </table>
        </div>
        <div class="ct-card">
            <h3>Pasivo y patrimonio neto</h3>
            <table class="ct-table">
                <tr class="ct-sub"><td colspan="2">Pasivo</td></tr>
                @foreach($b['pasivo'] as $f)<tr><td>{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->importe) }}</td></tr>@endforeach
                <tr class="ct-sub"><td>Total pasivo</td><td class="ct-num">{{ $fmt($b['total_pasivo']) }}</td></tr>
                <tr class="ct-sub"><td colspan="2">Patrimonio neto</td></tr>
                @foreach($b['patrimonio'] as $f)<tr><td>{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->importe) }}</td></tr>@endforeach
                <tr><td>Resultado acumulado hasta la fecha</td><td class="ct-num">{{ $fmt($b['resultado']) }}</td></tr>
                <tr class="ct-sub"><td>Total patrimonio neto</td><td class="ct-num">{{ $fmt($b['total_patrimonio']) }}</td></tr>
                <tr class="ct-sub"><td>TOTAL PASIVO + PATRIMONIO</td><td class="ct-num">{{ $fmt($b['total_pasivo'] + $b['total_patrimonio']) }}</td></tr>
            </table>
        </div>
    </div>
    @if($b['cuadra'])
        <p><span class="ct-badge ok">El balance cuadra</span></p>
    @else
        <div class="ct-alert bad">El balance no cuadra: falta el asiento de apertura con los saldos iniciales (caja, inventario, deudas y capital) o hay un asiento mal cargado.</div>
    @endif
</div>
@endsection
