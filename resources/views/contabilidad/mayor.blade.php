@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Libro mayor</h2><p class="ct-sub">Movimientos y saldo corrido de una cuenta.</p></div></div>
    @include('contabilidad._menu')

    <form method="GET" class="ct-card">
        <div class="ct-grid">
            <div style="grid-column:span 2"><label class="ct-label">Cuenta</label>
                <select name="cuenta" class="ct-in" required><option value="">Elegí una cuenta…</option>
                    @foreach($cuentas as $c)<option value="{{ $c->cue_id }}" @selected($cuentaId === (int) $c->cue_id)>{{ $c->cue_codigo }} · {{ $c->cue_nombre }}</option>@endforeach</select></div>
            <div><label class="ct-label">Desde</label><input type="date" name="desde" class="ct-in" value="{{ $desde }}"></div>
            <div><label class="ct-label">Hasta</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px"><button class="ct-btn" type="submit">Ver</button>
            @if($m)<a class="ct-btn ghost" href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}">Descargar CSV</a>@endif</div>
    </form>

    @if($m)
    <div class="ct-card ct-tw">
        <h3>{{ $m['cuenta']->cue_codigo }} · {{ $m['cuenta']->cue_nombre }}</h3>
        <table class="ct-table">
            <thead><tr><th>Fecha</th><th>Asiento</th><th>Concepto</th><th class="ct-r">Debe</th><th class="ct-r">Haber</th><th class="ct-r">Saldo</th></tr></thead>
            <tbody>
                <tr class="ct-sub"><td colspan="5">Saldo anterior al {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}</td><td class="ct-num">{{ $fmt($m['anterior']) }}</td></tr>
                @foreach($m['filas'] as $f)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($f->asi_fecha)->format('d/m/Y') }}</td>
                        <td>N° {{ $f->asi_numero }}</td>
                        <td>{{ $f->asi_glosa }}</td>
                        <td class="ct-num">{{ $f->lin_debe > 0 ? $fmt($f->lin_debe) : '' }}</td>
                        <td class="ct-num">{{ $f->lin_haber > 0 ? $fmt($f->lin_haber) : '' }}</td>
                        <td class="ct-num"><b>{{ $fmt($f->saldo) }}</b></td>
                    </tr>
                @endforeach
                <tr class="ct-sub"><td colspan="3">Totales y saldo final</td><td class="ct-num">{{ $fmt($m['debe']) }}</td><td class="ct-num">{{ $fmt($m['haber']) }}</td><td class="ct-num">{{ $fmt($m['saldo']) }}</td></tr>
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
