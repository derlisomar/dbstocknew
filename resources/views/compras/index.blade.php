@extends('layouts.admin')

@section('contenido')
@include('compras._estilos')
@php $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.'); @endphp
<div class="p5">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">Compras a proveedores</h2>
            <p class="p5-sub">Cada compra suma stock, actualiza el costo del producto y genera lo que se le debe al proveedor.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @can('PAGOS_PROVEEDORES')<a class="p5-btn ghost" href="{{ route('cuentas_pagar.index') }}">Cuentas a pagar</a>@endcan
            @can('COMPRAS_REGISTRAR')<a class="p5-btn" href="{{ route('compras.create') }}">+ Registrar compra</a>@endcan
        </div>
    </div>

    @if(session('success'))<div class="p5-alert ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <form method="GET" action="{{ route('compras.index') }}" class="p5-card">
        <div class="p5-grid">
            <div><label class="p5-label">Proveedor</label>
                <select name="prov_id" class="p5-in"><option value="">Todos</option>
                    @foreach($proveedores as $p)<option value="{{ $p->prov_id }}" @selected((string) request('prov_id') === (string) $p->prov_id)>{{ $p->prov_razonsocial }}</option>@endforeach
                </select></div>
            <div><label class="p5-label">Estado</label>
                <select name="estado" class="p5-in"><option value="">Todos</option>
                    <option value="REGISTRADA" @selected(request('estado') === 'REGISTRADA')>Registradas</option>
                    <option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anuladas</option></select></div>
            <div><label class="p5-label">Tipo</label>
                <select name="tipo" class="p5-in"><option value="">Todos</option>
                    <option value="CONTADO" @selected(request('tipo') === 'CONTADO')>Contado</option>
                    <option value="CREDITO" @selected(request('tipo') === 'CREDITO')>Crédito</option></select></div>
            <div><label class="p5-label">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="p5-in"></div>
            <div><label class="p5-label">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="p5-in"></div>
            <div><label class="p5-label">Nº de documento</label><input type="text" name="q" value="{{ request('q') }}" maxlength="40" class="p5-in"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
            <button class="p5-btn" type="submit">Filtrar</button>
            @if(request()->query())<a class="p5-btn ghost" href="{{ route('compras.index') }}">Limpiar</a>@endif
        </div>
    </form>

    <div class="p5-stats">
        <div class="p5-stat info"><span class="p5-mu">Compras vigentes con estos filtros</span><b>{{ $fmt($totalVigente) }}</b></div>
        <div class="p5-stat"><span class="p5-mu">Registros</span><b>{{ $compras->total() }}</b></div>
    </div>

    <div class="p5-card" style="padding:0">
        <div class="p5-tw">
            <table class="p5-table">
                <thead><tr>
                    <th>Nº</th><th>Fecha</th><th>Proveedor</th><th>Documento</th><th>Tipo</th>
                    <th class="p5-r">Total</th><th class="p5-r">Saldo a pagar</th><th>Estado</th><th></th>
                </tr></thead>
                <tbody>
                @forelse($compras as $c)
                    <tr>
                        <td><b>#{{ $c->com_id }}</b></td>
                        <td>{{ $c->com_fecha->format('d/m/Y') }}</td>
                        <td>{{ $c->proveedor->prov_razonsocial ?? '—' }}</td>
                        <td>{{ $c->com_nro_documento ?: '—' }}</td>
                        <td><span class="p5-badge {{ $c->com_tipo === 'CREDITO' ? 'info' : 'mute' }}">{{ $c->com_tipo === 'CREDITO' ? 'Crédito' : 'Contado' }}</span></td>
                        <td class="p5-r">{{ $fmt($c->com_total) }}</td>
                        <td class="p5-r">
                            @if($c->com_estado === 'ANULADA') — @else {{ $fmt($c->cuenta->cpa_saldo_pendiente ?? 0) }} @endif
                        </td>
                        <td>
                            @if($c->com_estado === 'ANULADA')<span class="p5-badge bad">Anulada</span>
                            @elseif(($c->cuenta->cpa_estado ?? '') === 'PENDIENTE')<span class="p5-badge warn">Con deuda</span>
                            @else<span class="p5-badge ok">Pagada</span>@endif
                        </td>
                        <td class="p5-r"><a class="p5-btn ghost sm" href="{{ route('compras.show', $c->com_id) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p5-c p5-mu" style="padding:28px">No hay compras con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px">{{ $compras->links() }}</div>
    </div>
</div>
@endsection
