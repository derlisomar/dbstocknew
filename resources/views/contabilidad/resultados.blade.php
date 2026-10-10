@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Estado de resultados</h2><p class="ct-sub">Ingresos menos costos y gastos del período: la ganancia o pérdida.</p></div></div>
    @include('contabilidad._menu')
    <form method="GET" class="ct-card"><div class="ct-grid">
        <div><label class="ct-label">Desde</label><input type="date" name="desde" class="ct-in" value="{{ $desde }}"></div>
        <div><label class="ct-label">Hasta</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div></div>
        <div style="margin-top:12px"><button class="ct-btn" type="submit">Ver</button></div></form>

    <div class="ct-card">
        <table class="ct-table">
            <tr class="ct-sub"><td colspan="2">Ingresos</td></tr>
            @foreach($r['ingresos'] as $f)<tr><td style="padding-left:24px">{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->importe) }}</td></tr>@endforeach
            <tr class="ct-sub"><td>Total ingresos</td><td class="ct-num">{{ $fmt($r['total_ingresos']) }}</td></tr>
            <tr class="ct-sub"><td colspan="2">Costos y gastos</td></tr>
            @foreach($r['egresos'] as $f)<tr><td style="padding-left:24px">{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->importe) }}</td></tr>@endforeach
            <tr class="ct-sub"><td>Total costos y gastos</td><td class="ct-num">{{ $fmt($r['total_egresos']) }}</td></tr>
            <tr class="ct-sub"><td>{{ $r['resultado'] >= 0 ? 'GANANCIA DEL PERÍODO' : 'PÉRDIDA DEL PERÍODO' }}</td><td class="ct-num ct-total">{{ $fmt($r['resultado']) }}</td></tr>
        </table>
    </div>
</div>
@endsection
