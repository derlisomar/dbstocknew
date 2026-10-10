@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => $n == 0 ? '' : number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Balance de sumas y saldos</h2><p class="ct-sub">Total del debe y del haber de cada cuenta en el período. Si los totales no coinciden, hay un error que revisar.</p></div></div>
    @include('contabilidad._menu')
    <form method="GET" class="ct-card"><div class="ct-grid">
        <div><label class="ct-label">Desde</label><input type="date" name="desde" class="ct-in" value="{{ $desde }}"></div>
        <div><label class="ct-label">Hasta</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div></div>
        <div style="margin-top:12px;display:flex;gap:8px"><button class="ct-btn" type="submit">Ver</button>
        <a class="ct-btn ghost" href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}">Descargar CSV</a></div></form>

    <div class="ct-card ct-tw">
        <table class="ct-table">
            <thead><tr><th>Código</th><th>Cuenta</th><th class="ct-r">Debe</th><th class="ct-r">Haber</th><th class="ct-r">Saldo deudor</th><th class="ct-r">Saldo acreedor</th></tr></thead>
            <tbody>
            @forelse($filas as $f)
                <tr><td>{{ $f->cue_codigo }}</td><td>{{ $f->cue_nombre }}</td><td class="ct-num">{{ $fmt($f->debe) }}</td><td class="ct-num">{{ $fmt($f->haber) }}</td><td class="ct-num">{{ $fmt($f->saldo_deudor) }}</td><td class="ct-num">{{ $fmt($f->saldo_acreedor) }}</td></tr>
            @empty
                <tr><td colspan="6" class="ct-mu">Sin movimientos en el período.</td></tr>
            @endforelse
            @php $td = $filas->sum('debe'); $th = $filas->sum('haber'); @endphp
            <tr class="ct-sub"><td colspan="2">Totales</td><td class="ct-num">{{ number_format($td, 0, ',', '.') }}</td><td class="ct-num">{{ number_format($th, 0, ',', '.') }}</td><td class="ct-num">{{ number_format($filas->sum('saldo_deudor'), 0, ',', '.') }}</td><td class="ct-num">{{ number_format($filas->sum('saldo_acreedor'), 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
        <p class="ct-hint">{!! abs($td - $th) < 0.05 ? '<span class="ct-badge ok">Debe y haber coinciden</span>' : '<span class="ct-badge bad">No coinciden</span>' !!}</p>
    </div>
</div>
@endsection
