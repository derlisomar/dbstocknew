@extends('layouts.admin')

@section('contenido')
@include('compras._estilos')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $cuenta = $compra->cuenta;
    $anulada = $compra->com_estado === 'ANULADA';
    $hayParaDevolver = $compra->detalles->contains(fn ($d) => (float) $d->dco_cantidad - (float) $d->dco_devuelta > 0);
@endphp
<div class="p5">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">Compra #{{ $compra->com_id }}
                @if($anulada)<span class="p5-badge bad">Anulada</span>@endif</h2>
            <p class="p5-sub">{{ $compra->proveedor->prov_razonsocial ?? '—' }} · {{ $compra->com_fecha->format('d/m/Y') }} · {{ $compra->com_tipo === 'CREDITO' ? 'A crédito' : 'Al contado' }}
                @if($compra->com_nro_documento) · Documento {{ $compra->com_nro_documento }}@endif</p>
        </div>
        <a class="p5-btn ghost" href="{{ route('compras.index') }}">Volver al listado</a>
    </div>

    @if(session('success'))<div class="p5-alert ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="p5-stats">
        <div class="p5-stat info"><span class="p5-mu">Total de la compra</span><b>{{ $fmt($compra->com_total) }}</b></div>
        @if($cuenta && ! $anulada)
            <div class="p5-stat {{ (float) $cuenta->cpa_saldo_pendiente > 0 ? 'warn' : 'ok' }}"><span class="p5-mu">Saldo a pagar</span><b>{{ $fmt($cuenta->cpa_saldo_pendiente) }}</b></div>
            @if($cuenta->cpa_fecha_vencimiento)<div class="p5-stat"><span class="p5-mu">Vence</span><b>{{ $cuenta->cpa_fecha_vencimiento->format('d/m/Y') }}</b></div>@endif
        @endif
        <div class="p5-stat"><span class="p5-mu">Cargada por</span><b style="font-size:14px">{{ $compra->usuario->usu_usuario ?? '—' }}</b></div>
    </div>

    @if($compra->com_observacion)<div class="p5-card"><span class="p5-label">Observación</span>{{ $compra->com_observacion }}</div>@endif
    @if($anulada)
        <div class="p5-alert bad">Anulada el {{ \Carbon\Carbon::parse($compra->com_anulada_fecha)->format('d/m/Y H:i') }}. Motivo: {{ $compra->com_motivo_anulacion }}</div>
    @endif

    <div class="p5-card" style="padding:0">
        <div class="p5-tw">
            <table class="p5-table">
                <thead><tr><th>Producto</th><th class="p5-r">Cantidad</th><th class="p5-r">Costo unitario</th><th class="p5-r">Subtotal</th><th class="p5-r">Devuelto</th></tr></thead>
                <tbody>
                @foreach($compra->detalles as $d)
                    <tr>
                        <td><b>{{ $d->producto->pro_nombre ?? ('#'.$d->pro_id) }}</b><div class="p5-mu">{{ $d->producto->pro_codigo ?? '' }}</div></td>
                        <td class="p5-r">{{ (float) $d->dco_cantidad }}</td>
                        <td class="p5-r">{{ $fmt($d->dco_costo) }}</td>
                        <td class="p5-r">{{ $fmt($d->dco_subtotal) }}</td>
                        <td class="p5-r">{{ (float) $d->dco_devuelta > 0 ? (float) $d->dco_devuelta : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($cuenta)
    <div class="p5-card">
        <h3>Pagos a proveedor</h3>
        @if($cuenta->pagos->isEmpty())
            <p class="p5-mu">Todavía no hay pagos registrados.</p>
        @else
        <div class="p5-tw"><table class="p5-table">
            <thead><tr><th>Fecha</th><th>Forma</th><th>Referencia</th><th>Registró</th><th class="p5-r">Monto</th><th>Estado</th></tr></thead>
            <tbody>
            @foreach($cuenta->pagos as $p)
                <tr>
                    <td>{{ $p->pag_fecha->format('d/m/Y H:i') }}</td>
                    <td>{{ ucfirst(strtolower($p->pag_forma_pago)) }}</td>
                    <td>{{ $p->pag_referencia ?: '—' }}</td>
                    <td>{{ $p->usuario->usu_usuario ?? '—' }}</td>
                    <td class="p5-r">{{ $fmt($p->pag_monto) }}</td>
                    <td>@if($p->pag_estado === 'ANULADO')<span class="p5-badge bad">Anulado</span>@else<span class="p5-badge ok">Activo</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
        @can('PAGOS_PROVEEDORES')
            @if(! $anulada && $cuenta->cpa_estado === 'PENDIENTE')
                <p style="margin-top:12px"><a class="p5-btn sm" href="{{ route('cuentas_pagar.index', ['prov_id' => $compra->prov_id]) }}">Ir a cuentas a pagar para registrar un pago</a></p>
            @endif
        @endcan
    </div>
    @endif

    @can('COMPRAS_ANULAR')
    @if(! $anulada)
    <div class="p5-card">
        <h3>Correcciones</h3>

        @if($hayParaDevolver)
        <details>
            <summary>Devolver mercadería al proveedor</summary>
            <form method="POST" action="{{ route('compras.devolver', $compra->com_id) }}" class="p5-pop" style="margin-top:8px">
                @csrf
                <p class="p5-mu" style="margin-bottom:8px">Indicá cuánto devolvés de cada producto. Baja el stock y se reduce lo que le debés al proveedor.</p>
                @foreach($compra->detalles as $d)
                    @php $quedan = round((float) $d->dco_cantidad - (float) $d->dco_devuelta, 2); @endphp
                    @if($quedan > 0)
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                        <span style="flex:1;min-width:160px">{{ $d->producto->pro_nombre ?? ('#'.$d->pro_id) }} <span class="p5-mu">(se puede devolver hasta {{ $quedan }})</span></span>
                        <input type="number" step="0.01" min="0" max="{{ $quedan }}" name="items[{{ $d->dco_id }}]" class="p5-in" style="width:110px" placeholder="0">
                    </div>
                    @endif
                @endforeach
                <input type="text" name="motivo" maxlength="200" class="p5-in" placeholder="Motivo (opcional)" style="margin:8px 0">
                <button class="p5-btn" type="submit">Registrar devolución</button>
            </form>
        </details>
        @endif

        <details style="margin-top:12px">
            <summary style="color:#dc2626">Anular esta compra completa</summary>
            <form method="POST" action="{{ route('compras.anular', $compra->com_id) }}" class="p5-pop" style="margin-top:8px"
                  onsubmit="return confirm('¿Anular la compra completa? La mercadería sale del stock y se revierten los pagos.')">
                @csrf
                <p class="p5-mu" style="margin-bottom:8px">La mercadería sale del stock y los pagos de esta compra se anulan (si se pagó en efectivo, vuelve a la caja). No se puede si parte de la mercadería ya se vendió.</p>
                <input type="text" name="motivo" maxlength="200" required class="p5-in" placeholder="Motivo de la anulación *" style="margin-bottom:8px">
                <button class="p5-btn danger" type="submit">Anular compra</button>
            </form>
        </details>
    </div>
    @endif
    @endcan
</div>
@endsection
