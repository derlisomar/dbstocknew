@extends('vendedor.layout')
@section('titulo', 'Historial')
@section('contenido')
@php
    $nombres = ['VENDEDOR_PLAN' => 'Plan de pago', 'VENDEDOR_NEGOCIO' => 'Datos del negocio', 'VENDEDOR_EDICION' => 'Edición', 'VENDEDOR_MODULOS' => 'Módulos y límites', 'VENDEDOR_PREPARAR_BASE' => 'Preparar base', 'VENDEDOR_RESPALDO' => 'Respaldo creado', 'VENDEDOR_RESPALDO_DESCARGA' => 'Respaldo descargado', 'VENDEDOR_AVISO_COBRO' => 'Aviso de cobro', 'VENDEDOR_INSTALAR' => 'Instalación'];
@endphp
<div class="pv-head"><div><h1>Historial de cambios</h1><p class="pv-sub">Todo lo que se hizo desde este panel, con fecha y dirección IP.</p></div></div>
<div class="pv-card">
    <div class="tw"><table>
        <thead><tr><th>Fecha</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($filas as $f)
            @php $det = json_decode((string) $f->aud_detalle, true); @endphp
            <tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($f->aud_fecha)->format('d/m/Y H:i') }}</td>
                <td><span class="badge b-info">{{ $nombres[$f->aud_accion] ?? $f->aud_accion }}</span></td>
                <td class="mu">@if(is_array($det)){{ collect($det)->except('por')->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))->implode(' · ') }}@else{{ $f->aud_detalle }}@endif</td>
                <td class="mu">{{ $f->aud_ip }}</td></tr>
        @empty<tr><td colspan="4" class="vacio">Todavía no hay cambios registrados.</td></tr>@endforelse
        </tbody></table></div>
    <div class="pager">
        <div>@if($filas->previousPageUrl())<a class="btn g sm" href="{{ $filas->previousPageUrl() }}">← Más nuevos</a>@endif</div>
        <div>@if($filas->nextPageUrl())<a class="btn g sm" href="{{ $filas->nextPageUrl() }}">Más antiguos →</a>@endif</div>
    </div>
</div>
@endsection
