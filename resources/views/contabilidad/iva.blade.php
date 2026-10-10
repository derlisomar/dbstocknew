@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => $n == 0 ? '' : number_format((float) $n, 0, ',', '.'); $esVentas = $libro === 'ventas'; @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Libro IVA {{ $esVentas ? 'ventas' : 'compras' }}</h2>
        <p class="ct-sub">Detalle por comprobante con la base gravada y el IVA al 10 % y al 5 %, para la declaración mensual.</p></div></div>
    @include('contabilidad._menu')

    <form method="GET" class="ct-card">
        <div class="ct-grid">
            <div><label class="ct-label">Desde</label><input type="date" name="desde" class="ct-in" value="{{ $desde }}"></div>
            <div><label class="ct-label">Hasta</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap"><button class="ct-btn" type="submit">Ver</button>
            <a class="ct-btn ghost" href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}">Descargar planilla (CSV)</a></div>
        <p class="ct-hint">La planilla trae las columnas estándar del libro (fecha, timbrado, número, RUC, gravado, IVA, exento). El formato exacto de importación de la SET puede cambiar según la versión del sistema Hechauka/Marangatu: confirmalo con tu contador antes de presentar.</p>
    </form>

    <div class="ct-card ct-tw">
        <table class="ct-table">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Timbrado</th><th>Número</th><th>RUC/CI</th><th>{{ $esVentas ? 'Cliente' : 'Proveedor' }}</th>
                <th class="ct-r">Gravado 10%</th><th class="ct-r">IVA 10%</th><th class="ct-r">Gravado 5%</th><th class="ct-r">IVA 5%</th><th class="ct-r">Exento</th><th class="ct-r">Total</th></tr></thead>
            <tbody>
            @forelse($filas as $f)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $f['tipo'] }}</td><td>{{ $f['timbrado'] ?: '—' }}</td><td>{{ $f['numero'] }}</td><td>{{ $f['ruc'] }}</td><td>{{ $f['nombre'] }}</td>
                    <td class="ct-num">{{ $fmt($f['base10']) }}</td><td class="ct-num">{{ $fmt($f['iva10']) }}</td>
                    <td class="ct-num">{{ $fmt($f['base5']) }}</td><td class="ct-num">{{ $fmt($f['iva5']) }}</td>
                    <td class="ct-num">{{ $fmt($f['exento']) }}</td><td class="ct-num"><b>{{ $fmt($f['total']) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="12" class="ct-mu">No hay comprobantes en el período.</td></tr>
            @endforelse
            <tr class="ct-sub"><td colspan="6">Totales</td>
                @foreach(['base10', 'iva10', 'base5', 'iva5', 'exento', 'total'] as $k)<td class="ct-num">{{ number_format($tot[$k], 0, ',', '.') }}</td>@endforeach</tr>
            </tbody>
        </table>
        <p class="ct-hint">IVA {{ $esVentas ? 'débito' : 'crédito' }} del período: <b>Gs. {{ number_format($tot['iva10'] + $tot['iva5'], 0, ',', '.') }}</b>.
            @if($esVentas) Las devoluciones figuran restando. @endif</p>
    </div>
</div>
@endsection
