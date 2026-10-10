@extends('vendedor.layout')
@section('titulo', 'Planes y precios')
@section('contenido')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $p = $editar;
    $extras = $p ? $p->modulosExtra() : [];
    $publicos = $planes->where('plan_publico', true)->where('plan_activo', true);
@endphp
<div class="pv-head">
    <div><h1>Planes y precios</h1>
    <p class="pv-sub">Lo que vendés. Los planes marcados como «públicos» aparecen en la sección de precios de la página web. Si ninguno es público, la web muestra las dos ediciones sin precio.</p></div>
    <a class="btn g" href="{{ \App\Support\Dominios::urlWeb('/') }}#planes" target="_blank" rel="noopener">Ver en la web</a>
</div>

<div class="pv-card">
    <h2>Así se ve en la web <span class="badge {{ $publicos->isEmpty() ? 'b-mu' : 'b-ok' }}">{{ $publicos->count() }} público(s)</span></h2>
    @if($publicos->isEmpty())
        <div class="vacio">Todavía no publicaste ningún plan. Editá uno, cargale el precio y marcá «Mostrar en la página web».</div>
    @else
    <div class="pv-prev">
        @foreach($publicos->sortBy('plan_orden') as $pl)
            <div class="pp {{ $pl->plan_destacado ? 'main' : '' }}">
                @if($pl->plan_etiqueta)<span class="tag">{{ $pl->plan_etiqueta }}</span>@endif
                <h3>{{ $pl->plan_nombre }}</h3>
                <div class="pr">{{ (float) $pl->plan_precio > 0 ? $fmt($pl->plan_precio) : 'A consultar' }} <span class="mu">{{ (float) $pl->plan_precio > 0 ? $pl->periodoTexto() : '' }}</span></div>
                <div class="mu">{{ $pl->plan_max_sucursales ?: '∞' }} suc. · {{ $pl->plan_max_cajas ?: '∞' }} puntos de venta · {{ $pl->plan_max_usuarios ?: '∞' }} usuarios</div>
                <ul>@foreach($pl->caracteristicas() as $c)<li class="{{ $c['incluye'] ? '' : 'off' }}">{{ $c['incluye'] ? '✓' : '✕' }} {{ $c['texto'] }}</li>@endforeach</ul>
            </div>
        @endforeach
    </div>
    @endif
</div>

<div class="pv-card">
    <h2>Planes</h2>
    <div class="tw"><table>
        <thead><tr><th>Orden</th><th>Plan</th><th>Edición</th><th class="r">Precio</th><th>Límites (suc. / cajas / usuarios)</th><th>Web</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @foreach($planes as $pl)
            <tr><td>{{ $pl->plan_orden }}</td>
                <td><b>{{ $pl->plan_nombre }}</b>@if($pl->plan_destacado) <span class="badge b-info">{{ $pl->plan_etiqueta ?: 'Destacado' }}</span>@endif<div class="mu">{{ $pl->plan_descripcion }}</div></td>
                <td>{{ config('modulos.ediciones')[$pl->plan_edicion] ?? $pl->plan_edicion }}@if($pl->modulosExtra())<div class="mu">+ {{ count($pl->modulosExtra()) }} módulo(s) extra</div>@endif</td>
                <td class="r" style="white-space:nowrap">{{ $fmt($pl->plan_precio) }}<div class="mu">{{ $pl->plan_meses == 0 ? 'pago único' : ($pl->plan_meses == 1 ? 'cada mes' : 'cada '.$pl->plan_meses.' meses') }}</div></td>
                <td>{{ $pl->plan_max_sucursales ?: '∞' }} / {{ $pl->plan_max_cajas ?: '∞' }} / {{ $pl->plan_max_usuarios ?: '∞' }}</td>
                <td><span class="badge {{ $pl->plan_publico ? 'b-ok' : 'b-mu' }}">{{ $pl->plan_publico ? 'Público' : 'Oculto' }}</span></td>
                <td><span class="badge {{ $pl->plan_activo ? 'b-ok' : 'b-mu' }}">{{ $pl->plan_activo ? 'Activo' : 'Inactivo' }}</span></td>
                <td class="r" style="white-space:nowrap"><a class="btn g sm" href="{{ route('vendedor.planes', ['editar' => $pl->plan_id]) }}#form-plan">Editar</a>
                    <form method="POST" action="{{ route('vendedor.planes.estado', $pl->plan_id) }}" style="display:inline">@csrf<button class="btn g sm">{{ $pl->plan_activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @endforeach
        </tbody></table></div>
</div>

<form method="POST" id="form-plan" action="{{ $p ? route('vendedor.planes.editar', $p->plan_id) : route('vendedor.planes.crear') }}" class="pv-card">
    @csrf @if($p) @method('PUT') @endif
    <h2>{{ $p ? 'Editar plan «'.$p->plan_nombre.'»' : 'Nuevo plan' }}</h2>
    <div class="pv-grid">
        <div><label class="l">Nombre *</label><input class="in" name="plan_nombre" value="{{ old('plan_nombre', $p->plan_nombre ?? '') }}" required maxlength="80"></div>
        <div><label class="l">Edición base</label><select class="in" name="plan_edicion">@foreach(config('modulos.ediciones') as $k => $n)<option value="{{ $k }}" @selected(old('plan_edicion', $p->plan_edicion ?? 'BASICA') === $k)>{{ $n }}</option>@endforeach</select></div>
        <div><label class="l">Precio (Gs.)</label><input class="in" type="number" min="0" step="1" name="plan_precio" value="{{ old('plan_precio', (int) ($p->plan_precio ?? 0)) }}" required><span class="mu">0 = «A consultar»</span></div>
        <div><label class="l">Se paga cada (meses)</label><input class="in" type="number" min="0" max="60" name="plan_meses" value="{{ old('plan_meses', $p->plan_meses ?? 1) }}" required><span class="mu">1 = mensual, 12 = anual, 0 = pago único</span></div>
        <div><label class="l">Sucursales (0 = ilimitadas)</label><input class="in" type="number" min="0" name="plan_max_sucursales" value="{{ old('plan_max_sucursales', $p->plan_max_sucursales ?? 0) }}"></div>
        <div><label class="l">Puntos de venta / cajas</label><input class="in" type="number" min="0" name="plan_max_cajas" value="{{ old('plan_max_cajas', $p->plan_max_cajas ?? 0) }}"></div>
        <div><label class="l">Usuarios</label><input class="in" type="number" min="0" name="plan_max_usuarios" value="{{ old('plan_max_usuarios', $p->plan_max_usuarios ?? 0) }}"></div>
    </div>
    <div style="margin-top:14px"><label class="l">Descripción corta</label><input class="in" name="plan_descripcion" value="{{ old('plan_descripcion', $p->plan_descripcion ?? '') }}" maxlength="255"></div>

    <h2 style="margin-top:22px">En la página web</h2>
    <div class="pv-grid">
        <div><label class="l">Orden</label><input class="in" type="number" min="0" max="999" name="plan_orden" value="{{ old('plan_orden', $p->plan_orden ?? 0) }}"></div>
        <div><label class="l">Etiqueta (ej. Recomendado)</label><input class="in" name="plan_etiqueta" maxlength="30" value="{{ old('plan_etiqueta', $p->plan_etiqueta ?? '') }}"></div>
        <div><label class="l">Texto del botón</label><input class="in" name="plan_boton" maxlength="40" placeholder="Probar este plan" value="{{ old('plan_boton', $p->plan_boton ?? '') }}"></div>
    </div>
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-top:14px">
        <label class="sw"><input type="checkbox" name="plan_publico" value="1" @checked(old('plan_publico', $p->plan_publico ?? false))> Mostrar en la página web</label>
        <label class="sw"><input type="checkbox" name="plan_destacado" value="1" @checked(old('plan_destacado', $p->plan_destacado ?? false))> Destacar este plan</label>
    </div>
    <div style="margin-top:14px"><label class="l">Lista de características (una por línea; con «-» al principio sale tachada)</label>
        <textarea class="in" name="plan_caracteristicas" rows="8" maxlength="3000">{{ old('plan_caracteristicas', $p->plan_caracteristicas ?? '') }}</textarea></div>

    <div style="margin-top:14px"><label class="l">Módulos extra además de los de la edición</label>
        <div class="pv-grid">
            @foreach(config('modulos.catalogo') as $k => $m)
                <label class="mod"><input type="checkbox" name="plan_modulos[]" value="{{ $k }}" @checked(in_array($k, old('plan_modulos', $extras), true))><span><b>{{ $m['nombre'] }}</b></span></label>
            @endforeach
        </div></div>
    <div class="acciones" style="margin-top:18px"><button class="btn ok">{{ $p ? 'Guardar plan' : 'Crear plan' }}</button>@if($p)<a class="btn g" href="{{ route('vendedor.planes') }}">Cancelar</a>@endif</div>
</form>

<form method="POST" action="{{ route('vendedor.planes.adicionales') }}" class="pv-card">
    @csrf
    <h2>Capacidad flexible y textos de la sección</h2>
    <p class="mu" style="margin-top:-6px">Los adicionales se muestran en la web solo si están activos y tienen precio.</p>
    <div class="pv-grid" style="grid-template-columns:1fr 1fr">
        <div><label class="l">Título de la sección de precios</label><input class="in" name="pub_planes_titulo" maxlength="140" value="{{ old('pub_planes_titulo', $titulo) }}" placeholder="Planes simples, para cada tamaño de negocio."></div>
        <div><label class="l">Texto debajo del título</label><input class="in" name="pub_planes_texto" maxlength="300" value="{{ old('pub_planes_texto', $texto) }}"></div>
    </div>
    <div class="tw" style="margin-top:14px"><table>
        <thead><tr><th>Adicional</th><th>Precio (Gs.)</th><th>Cada</th><th>Orden</th><th>Activo</th><th>Quitar</th></tr></thead>
        <tbody>
        @foreach($adicionales as $ad)
            <tr><td><input class="in" name="ad[{{ $ad->ada_id }}][nombre]" value="{{ $ad->ada_nombre }}" maxlength="80"></td>
                <td><input class="in" type="number" min="0" step="1" name="ad[{{ $ad->ada_id }}][precio]" value="{{ (int) $ad->ada_precio }}" style="width:130px"></td>
                <td><input class="in" name="ad[{{ $ad->ada_id }}][periodo]" value="{{ $ad->ada_periodo }}" maxlength="20" style="width:90px"></td>
                <td><input class="in" type="number" min="0" name="ad[{{ $ad->ada_id }}][orden]" value="{{ $ad->ada_orden }}" style="width:70px"></td>
                <td><input type="checkbox" name="ad[{{ $ad->ada_id }}][activo]" value="1" @checked($ad->ada_activo)></td>
                <td><input type="checkbox" name="ad[{{ $ad->ada_id }}][eliminar]" value="1"></td></tr>
        @endforeach
            <tr><td><input class="in" name="nuevo[nombre]" placeholder="Nuevo adicional (ej. Usuario adicional)" maxlength="80"></td>
                <td><input class="in" type="number" min="0" step="1" name="nuevo[precio]" placeholder="0" style="width:130px"></td>
                <td><input class="in" name="nuevo[periodo]" placeholder="mes" maxlength="20" style="width:90px"></td><td colspan="3" class="mu">Dejalo vacío si no querés agregar uno</td></tr>
        </tbody></table></div>
    <div style="margin-top:14px"><button class="btn ok">Guardar adicionales y textos</button></div>
</form>
@endsection
