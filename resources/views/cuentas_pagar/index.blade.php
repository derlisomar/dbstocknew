@extends('layouts.admin')

@section('contenido')
@include('compras._estilos')
@php $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.'); @endphp
<div class="p5">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">Cuentas a pagar</h2>
            <p class="p5-sub">Lo que se le debe a cada proveedor, con su antigüedad. Los pagos en efectivo salen de tu caja abierta.</p>
        </div>
        <a class="p5-btn ghost" href="{{ route('compras.index') }}">Compras</a>
    </div>

    @if(session('success'))<div class="p5-alert ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="p5-stats">
        <div class="p5-stat info"><span class="p5-mu">Deuda total con proveedores</span><b>{{ $fmt($deudaTotal) }}</b></div>
        <div class="p5-stat ok"><span class="p5-mu">Vigente (no vencida)</span><b>{{ $fmt($antiguedad['vigente']) }}</b></div>
        <div class="p5-stat warn"><span class="p5-mu">Vencida 1 a 30 días</span><b>{{ $fmt($antiguedad['d30']) }}</b></div>
        <div class="p5-stat warn"><span class="p5-mu">Vencida 31 a 60 días</span><b>{{ $fmt($antiguedad['d60']) }}</b></div>
        <div class="p5-stat bad"><span class="p5-mu">Vencida más de 60 días</span><b>{{ $fmt($antiguedad['d60mas']) }}</b></div>
    </div>

    @if(count($porProveedor))
    <div class="p5-card" style="padding:0">
        <div style="padding:12px 16px 0"><h3>Deuda por proveedor</h3></div>
        <div class="p5-tw"><table class="p5-table">
            <thead><tr><th>Proveedor</th><th class="p5-r">Cuentas</th><th class="p5-r">Deuda</th><th class="p5-r">De la cual vencida</th><th></th></tr></thead>
            <tbody>
            @foreach($porProveedor as $r)
                <tr>
                    <td><b>{{ $r['nombre'] }}</b></td>
                    <td class="p5-r">{{ $r['cuentas'] }}</td>
                    <td class="p5-r">{{ $fmt($r['deuda']) }}</td>
                    <td class="p5-r">@if($r['vencida'] > 0)<span class="p5-badge bad">{{ $fmt($r['vencida']) }}</span>@else — @endif</td>
                    <td class="p5-r"><a class="p5-btn ghost sm" href="{{ route('cuentas_pagar.index', ['prov_id' => $r['id']]) }}">Ver cuentas</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    @endif

    <form method="GET" action="{{ route('cuentas_pagar.index') }}" class="p5-card">
        <div class="p5-grid">
            <div><label class="p5-label">Proveedor</label>
                <select name="prov_id" class="p5-in"><option value="">Todos</option>
                    @foreach($proveedores as $p)<option value="{{ $p->prov_id }}" @selected((string) request('prov_id') === (string) $p->prov_id)>{{ $p->prov_razonsocial }}</option>@endforeach
                </select></div>
            <div><label class="p5-label">Mostrar</label>
                <select name="estado" class="p5-in">
                    <option value="PENDIENTE" @selected($estado === 'PENDIENTE')>Con deuda pendiente</option>
                    <option value="VENCIDAS" @selected($estado === 'VENCIDAS')>Solo vencidas</option>
                    <option value="PAGADA" @selected($estado === 'PAGADA')>Pagadas</option>
                    <option value="TODAS" @selected($estado === 'TODAS')>Todas</option>
                </select></div>
            <div style="display:flex;align-items:flex-end;gap:8px"><button class="p5-btn" type="submit">Filtrar</button>
                @if(request()->query())<a class="p5-btn ghost" href="{{ route('cuentas_pagar.index') }}">Limpiar</a>@endif</div>
        </div>
    </form>

    <div class="p5-card" style="padding:0">
        <div class="p5-tw"><table class="p5-table">
            <thead><tr><th>Compra</th><th>Proveedor</th><th>Vence</th><th class="p5-r">Total</th><th class="p5-r">Saldo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse($cuentas as $c)
                @php
                    $vencida = $c->cpa_estado === 'PENDIENTE' && $c->cpa_fecha_vencimiento && $c->cpa_fecha_vencimiento->toDateString() < $hoy;
                @endphp
                <tr>
                    <td><a href="{{ route('compras.show', $c->com_id) }}"><b>#{{ $c->com_id }}</b></a>
                        <div class="p5-mu">{{ $c->compra->com_nro_documento ?? '' }}</div></td>
                    <td>{{ $c->proveedor->prov_razonsocial ?? '—' }}</td>
                    <td>{{ $c->cpa_fecha_vencimiento ? $c->cpa_fecha_vencimiento->format('d/m/Y') : '—' }}
                        @if($vencida)<span class="p5-badge bad">Vencida</span>@endif</td>
                    <td class="p5-r">{{ $fmt($c->cpa_monto_total) }}</td>
                    <td class="p5-r"><b>{{ $fmt($c->cpa_saldo_pendiente) }}</b></td>
                    <td>@if($c->cpa_estado === 'PAGADA')<span class="p5-badge ok">Pagada</span>@else<span class="p5-badge warn">Pendiente</span>@endif</td>
                    <td class="p5-r" style="min-width:230px">
                        @can('PAGOS_PROVEEDORES')
                        @if($c->cpa_estado === 'PENDIENTE')
                        <details>
                            <summary>Registrar pago</summary>
                            <form method="POST" action="{{ route('cuentas_pagar.pagar', $c->cpa_id) }}" class="p5-pop" style="text-align:left">
                                @csrf
                                <label class="p5-label">Monto</label>
                                <input type="number" step="0.01" min="0.01" max="{{ (float) $c->cpa_saldo_pendiente }}" name="monto" value="{{ (float) $c->cpa_saldo_pendiente }}" class="p5-in" required>
                                <label class="p5-label" style="margin-top:8px">Forma de pago</label>
                                <select name="forma_pago" class="p5-in">
                                    @foreach($formas as $f)<option value="{{ $f }}">{{ ucfirst(strtolower($f)) }}</option>@endforeach
                                </select>
                                <label class="p5-label" style="margin-top:8px">Referencia (opcional)</label>
                                <input type="text" name="referencia" maxlength="120" class="p5-in" placeholder="Nº de transferencia o cheque">
                                <button class="p5-btn ok" type="submit" style="margin-top:10px">Pagar</button>
                            </form>
                        </details>
                        @endif
                        @php $activos = $c->pagos->where('pag_estado', 'ACTIVO'); @endphp
                        @if($activos->count())
                        <details>
                            <summary style="color:#dc2626">Anular un pago ({{ $activos->count() }})</summary>
                            @foreach($activos as $pg)
                            <form method="POST" action="{{ route('pagos_proveedores.anular', $pg->pag_id) }}" class="p5-pop" style="text-align:left;margin-bottom:6px"
                                  onsubmit="return confirm('¿Anular este pago?')">
                                @csrf
                                <div class="p5-mu" style="margin-bottom:6px">{{ $pg->pag_fecha->format('d/m/Y') }} · {{ $fmt($pg->pag_monto) }} · {{ ucfirst(strtolower($pg->pag_forma_pago)) }}</div>
                                <input type="text" name="motivo" maxlength="200" required class="p5-in" placeholder="Motivo *">
                                <button class="p5-btn danger sm" type="submit" style="margin-top:8px">Anular pago</button>
                            </form>
                            @endforeach
                        </details>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p5-c p5-mu" style="padding:28px">No hay cuentas con ese filtro.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="padding:12px">{{ $cuentas->links() }}</div>
    </div>
</div>
@endsection
