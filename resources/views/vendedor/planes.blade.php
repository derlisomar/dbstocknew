@extends('vendedor.layout')
@section('titulo', 'Planes')
@section('contenido')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $p = $editar;
    $extras = $p ? $p->modulosExtra() : [];
@endphp
<h1>Planes de pago</h1>
<p class="pv-sub">Definí lo que vendés: edición, módulos extra, límites, precio y cada cuánto se paga. Después le asignás un plan a cada negocio.</p>

<div class="pv-card">
    <h2>Planes</h2>
    <div class="tw"><table>
        <thead><tr><th>Plan</th><th>Edición</th><th class="r">Precio</th><th>Se paga</th><th>Límites (suc. / cajas / usuarios)</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @foreach($planes as $pl)
            <tr><td><b>{{ $pl->plan_nombre }}</b><div class="mu">{{ $pl->plan_descripcion }}</div></td>
                <td>{{ config('modulos.ediciones')[$pl->plan_edicion] ?? $pl->plan_edicion }}@if($pl->modulosExtra())<div class="mu">+ {{ count($pl->modulosExtra()) }} módulo(s) extra</div>@endif</td>
                <td class="r">{{ $fmt($pl->plan_precio) }}</td>
                <td>{{ $pl->plan_meses == 0 ? 'Pago único' : ($pl->plan_meses == 1 ? 'Cada mes' : 'Cada '.$pl->plan_meses.' meses') }}</td>
                <td>{{ $pl->plan_max_sucursales ?: '∞' }} / {{ $pl->plan_max_cajas ?: '∞' }} / {{ $pl->plan_max_usuarios ?: '∞' }}</td>
                <td><span class="badge {{ $pl->plan_activo ? 'b-ok' : 'b-mu' }}">{{ $pl->plan_activo ? 'Activo' : 'Inactivo' }}</span></td>
                <td class="r" style="white-space:nowrap"><a class="btn g sm" href="{{ route('vendedor.planes', ['editar' => $pl->plan_id]) }}">Editar</a>
                    <form method="POST" action="{{ route('vendedor.planes.estado', $pl->plan_id) }}" style="display:inline">@csrf<button class="btn g sm">{{ $pl->plan_activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @endforeach
        </tbody></table></div>
</div>

<form method="POST" action="{{ $p ? route('vendedor.planes.editar', $p->plan_id) : route('vendedor.planes.crear') }}" class="pv-card">
    @csrf @if($p) @method('PUT') @endif
    <h2>{{ $p ? 'Editar plan «'.$p->plan_nombre.'»' : 'Nuevo plan' }}</h2>
    <div class="pv-grid">
        <div><label class="l">Nombre *</label><input class="in" name="plan_nombre" value="{{ old('plan_nombre', $p->plan_nombre ?? '') }}" required maxlength="80"></div>
        <div><label class="l">Edición base</label><select class="in" name="plan_edicion">@foreach(config('modulos.ediciones') as $k => $n)<option value="{{ $k }}" @selected(old('plan_edicion', $p->plan_edicion ?? 'BASICA') === $k)>{{ $n }}</option>@endforeach</select></div>
        <div><label class="l">Precio (Gs.)</label><input class="in" type="number" min="0" step="1" name="plan_precio" value="{{ old('plan_precio', (int) ($p->plan_precio ?? 0)) }}" required></div>
        <div><label class="l">Se paga cada (meses; 0 = pago único)</label><input class="in" type="number" min="0" max="60" name="plan_meses" value="{{ old('plan_meses', $p->plan_meses ?? 1) }}" required></div>
        <div><label class="l">Máx. sucursales (0 = sin límite)</label><input class="in" type="number" min="0" name="plan_max_sucursales" value="{{ old('plan_max_sucursales', $p->plan_max_sucursales ?? 0) }}"></div>
        <div><label class="l">Máx. cajas</label><input class="in" type="number" min="0" name="plan_max_cajas" value="{{ old('plan_max_cajas', $p->plan_max_cajas ?? 0) }}"></div>
        <div><label class="l">Máx. usuarios</label><input class="in" type="number" min="0" name="plan_max_usuarios" value="{{ old('plan_max_usuarios', $p->plan_max_usuarios ?? 0) }}"></div>
    </div>
    <div style="margin-top:12px"><label class="l">Descripción</label><input class="in" name="plan_descripcion" value="{{ old('plan_descripcion', $p->plan_descripcion ?? '') }}" maxlength="255"></div>
    <div style="margin-top:12px"><label class="l">Módulos extra además de los de la edición</label>
        <div class="pv-grid">
            @foreach(config('modulos.catalogo') as $k => $m)
                <label class="mod"><input type="checkbox" name="plan_modulos[]" value="{{ $k }}" @checked(in_array($k, old('plan_modulos', $extras), true))><span><b>{{ $m['nombre'] }}</b></span></label>
            @endforeach
        </div></div>
    <div style="margin-top:16px;display:flex;gap:8px"><button class="btn ok">{{ $p ? 'Guardar plan' : 'Crear plan' }}</button>@if($p)<a class="btn g" href="{{ route('vendedor.planes') }}">Cancelar</a>@endif</div>
</form>
@endsection
