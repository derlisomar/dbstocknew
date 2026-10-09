@extends('vendedor.layout')
@section('titulo', 'Sucursales y cajas')
@section('contenido')
<h1>Sucursales, cajas y depósitos</h1>
<p class="pv-sub">Dejá lista la estructura del negocio para que pueda empezar a vender. Para el timbrado y la facturación, cargalo en la sucursal.</p>

<div class="pv-card">
    <h2>Sucursales</h2>
    <div class="tw"><table>
        <thead><tr><th>Nombre</th><th>Dirección</th><th>Teléfono</th><th>Punto exp.</th><th>Timbrado</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($sucursales as $s)
            <tr><td><b>{{ $s->suc_nombre }}</b></td><td>{{ $s->suc_direccion ?? '—' }}</td><td>{{ $s->suc_telefono ?? '—' }}</td><td>{{ $s->suc_est_punto_exp ?? '—' }}</td>
                <td>{{ $s->suc_timbrado ?? '—' }}</td>
                <td><span class="badge {{ $s->suc_activa ? 'b-ok' : 'b-mu' }}">{{ $s->suc_activa ? 'Activa' : 'Inactiva' }}</span></td>
                <td class="r"><form method="POST" action="{{ route('vendedor.sucursales.estado', $s->suc_id) }}">@csrf<button class="btn g sm">{{ $s->suc_activa ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @empty<tr><td colspan="7" class="mu">Todavía no hay sucursales.</td></tr>@endforelse
        </tbody></table></div>
    <form method="POST" action="{{ route('vendedor.sucursales.crear') }}" style="margin-top:14px">
        @csrf
        <div class="pv-grid">
            <div><label class="l">Nombre *</label><input class="in" name="suc_nombre" required maxlength="100"></div>
            <div><label class="l">Dirección</label><input class="in" name="suc_direccion" maxlength="200"></div>
            <div><label class="l">Teléfono</label><input class="in" name="suc_telefono" maxlength="30"></div>
            <div><label class="l">Establecimiento-punto</label><input class="in" name="suc_est_punto_exp" value="001-001" maxlength="10"></div>
            <div><label class="l">Timbrado (opcional)</label><input class="in" name="suc_timbrado" maxlength="20"></div>
            <div><label class="l">Vigente desde</label><input class="in" type="date" name="suc_timbrado_inicio"></div>
            <div><label class="l">Vigente hasta</label><input class="in" type="date" name="suc_timbrado_fin"></div>
        </div>
        <div style="margin-top:12px"><button class="btn ok">+ Crear sucursal</button></div>
    </form>
</div>

<div class="pv-card">
    <h2>Cajas</h2>
    <div class="tw"><table>
        <thead><tr><th>Caja</th><th>Sucursal</th><th>Comprobante</th><th>Impresora</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($cajas as $c)
            <tr><td><b>{{ $c->caj_nombre }}</b></td><td>{{ optional($sucursales->firstWhere('suc_id', $c->suc_id))->suc_nombre ?? '—' }}</td>
                <td>{{ $c->caj_tipo_impresion === 'TICKET_FACTURA' ? 'Factura' : 'Ticket simple' }}</td><td>{{ $c->caj_impresora ?? '—' }}</td>
                <td><span class="badge {{ $c->caj_activa ? 'b-ok' : 'b-mu' }}">{{ $c->caj_activa ? 'Activa' : 'Inactiva' }}</span></td>
                <td class="r"><form method="POST" action="{{ route('vendedor.cajas.estado', $c->caj_id) }}">@csrf<button class="btn g sm">{{ $c->caj_activa ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @empty<tr><td colspan="6" class="mu">Todavía no hay cajas.</td></tr>@endforelse
        </tbody></table></div>
    <form method="POST" action="{{ route('vendedor.cajas.crear') }}" style="margin-top:14px">
        @csrf
        <div class="pv-grid">
            <div><label class="l">Sucursal *</label><select class="in" name="suc_id" required><option value="">Elegí</option>@foreach($sucursales as $s)<option value="{{ $s->suc_id }}">{{ $s->suc_nombre }}</option>@endforeach</select></div>
            <div><label class="l">Nombre de la caja *</label><input class="in" name="caj_nombre" required maxlength="50" placeholder="Caja 1"></div>
            <div><label class="l">Comprobante</label><select class="in" name="caj_tipo_impresion"><option value="TICKET_SIMPLE">Ticket simple</option><option value="TICKET_FACTURA">Factura</option></select></div>
            <div><label class="l">Impresora</label><input class="in" name="caj_impresora" maxlength="100"></div>
        </div>
        <div style="margin-top:12px"><button class="btn ok">+ Crear caja</button></div>
    </form>
</div>

<div class="pv-card">
    <h2>Depósitos</h2>
    <div class="tw"><table>
        <thead><tr><th>Depósito</th><th>Sucursal</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($depositos as $d)
            <tr><td><b>{{ $d->dep_nombre }}</b><div class="mu">{{ $d->dep_descripcion ?? '' }}</div></td>
                <td>{{ optional($sucursales->firstWhere('suc_id', $d->suc_id))->suc_nombre ?? '—' }}</td>
                <td><span class="badge {{ $d->dep_activo ? 'b-ok' : 'b-mu' }}">{{ $d->dep_activo ? 'Activo' : 'Inactivo' }}</span></td>
                <td class="r"><form method="POST" action="{{ route('vendedor.depositos.estado', $d->dep_id) }}">@csrf<button class="btn g sm">{{ $d->dep_activo ? 'Desactivar' : 'Activar' }}</button></form></td></tr>
        @empty<tr><td colspan="4" class="mu">Sin depósitos.</td></tr>@endforelse
        </tbody></table></div>
    <form method="POST" action="{{ route('vendedor.depositos.crear') }}" style="margin-top:14px">
        @csrf
        <div class="pv-grid">
            <div><label class="l">Sucursal *</label><select class="in" name="suc_id" required><option value="">Elegí</option>@foreach($sucursales as $s)<option value="{{ $s->suc_id }}">{{ $s->suc_nombre }}</option>@endforeach</select></div>
            <div><label class="l">Nombre *</label><input class="in" name="dep_nombre" required maxlength="100"></div>
            <div><label class="l">Descripción</label><input class="in" name="dep_descripcion" maxlength="200"></div>
        </div>
        <div style="margin-top:12px"><button class="btn ok">+ Crear depósito</button></div>
    </form>
</div>

<div class="pv-card">
    <h2>Cotización de monedas</h2>
    <p class="mu">Necesaria solo si el negocio vende en dólares o reales (módulo "Ventas en dólares y reales").
        @if($cotizacion) Actual: 1 USD = Gs. {{ number_format($cotizacion->cot_dolar, 0, ',', '.') }} · 1 BRL = Gs. {{ number_format($cotizacion->cot_real, 0, ',', '.') }}.@endif</p>
    <form method="POST" action="{{ route('vendedor.cotizacion') }}">
        @csrf
        <div class="pv-grid">
            <div><label class="l">Dólar (Gs.)</label><input class="in" type="number" step="0.01" min="1" name="cot_dolar" value="{{ $cotizacion->cot_dolar ?? '' }}" required></div>
            <div><label class="l">Real (Gs.)</label><input class="in" type="number" step="0.01" min="1" name="cot_real" value="{{ $cotizacion->cot_real ?? '' }}" required></div>
        </div>
        <div style="margin-top:12px"><button class="btn ok">Guardar cotización</button></div>
    </form>
</div>
@endsection
