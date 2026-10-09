@extends('layouts.admin')

@section('contenido')
@include('presupuestos._estilos')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $badge = ['BORRADOR' => ['mute', 'Borrador'], 'ENVIADO' => ['info', 'Enviado'], 'ACEPTADO' => ['ok', 'Aceptado'],
              'RECHAZADO' => ['bad', 'Rechazado'], 'FACTURADO' => ['ok', 'Vendido'], 'VENCIDO' => ['bad', 'Vencido']];
    $ver = $p->estadoVisible();
    [$clase, $texto] = $badge[$ver];
    $dias = $p->diasRestantes();
    $gestiona = auth()->user()->can('PRESUPUESTOS_GESTIONAR');
@endphp
<div class="p5">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">{{ $p->numero }} <span class="p5-badge {{ $clase }}" style="vertical-align:middle">{{ $texto }}</span></h2>
            <p class="p5-sub">Para {{ $p->nombre_cliente }} · emitido el {{ $p->pre_fecha->setTimezone(config('presupuestos.zona'))->format('d/m/Y') }} por {{ $p->usuario->usu_usuario ?? '—' }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="p5-btn ghost" href="{{ route('presupuestos.index') }}">Volver al listado</a>
            <a class="p5-btn ghost" href="{{ route('presupuestos.imprimir', $p->pre_id) }}" target="_blank">Imprimir / PDF</a>
            @if($gestiona && in_array($p->pre_estado, ['BORRADOR', 'ENVIADO'], true))
                <a class="p5-btn ghost" href="{{ route('presupuestos.edit', $p->pre_id) }}">Editar</a>
            @endif
            @can('PDV_USAR')
                @if($convertible)<a class="p5-btn ok" href="{{ route('pdv.index', ['presupuesto' => $p->pre_id]) }}">Convertir en venta</a>@endif
            @endcan
        </div>
    </div>

    @if(session('success'))<div class="p5-alert ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    @if($p->pre_estado === 'FACTURADO')
        <div class="p5-alert ok">Se convirtió en la venta #{{ $p->vta_id }} el {{ optional($p->pre_facturado_fecha)->setTimezone(config('presupuestos.zona'))->format('d/m/Y H:i') }}.
            @can('VENTAS_HISTORIAL')<a href="{{ route('operaciones.ventas.ticket', $p->vta_id) }}" target="_blank" style="text-decoration:underline">Ver ticket</a>@endcan</div>
    @elseif($p->pre_estado === 'RECHAZADO')
        <div class="p5-alert bad">Rechazado{{ $p->pre_rechazo_motivo ? ': '.$p->pre_rechazo_motivo : '.' }}</div>
    @elseif($ver === 'VENCIDO')
        <div class="p5-alert bad">Venció el {{ $p->pre_fecha_vencimiento->format('d/m/Y') }}. No se puede vender hasta renovar la validez.</div>
    @endif

    <div class="p5-stats">
        <div class="p5-stat info"><span class="p5-mu">Total</span><b>{{ $fmt($p->pre_total) }}</b></div>
        <div class="p5-stat {{ $ver === 'VENCIDO' ? 'bad' : ($dias <= config('presupuestos.aviso_dias', 2) ? 'warn' : 'ok') }}">
            <span class="p5-mu">Vale hasta</span><b>{{ $p->pre_fecha_vencimiento->format('d/m/Y') }}</b>
            @if($p->estaAbierto())<span class="p5-mu">{{ $dias < 0 ? 'venció hace '.abs($dias).' d' : ($dias === 0 ? 'vence hoy' : 'faltan '.$dias.' días') }}</span>@endif
        </div>
        <div class="p5-stat"><span class="p5-mu">Validez original</span><b>{{ $p->pre_validez_dias }} días</b></div>
    </div>

    @if($p->estaAbierto() && count($cambios))
        <div class="p5-alert" style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.4);color:#92400e">
            Algunos precios cambiaron desde que se cotizó. Al vender se cobra el precio vigente de hoy (marcados abajo).
        </div>
    @endif

    <div class="p5-card" style="padding:0">
        <div class="p5-tw">
            <table class="p5-table">
                <thead><tr><th>Producto</th><th class="p5-r">Cantidad</th><th class="p5-r">Precio cotizado</th><th class="p5-r">Subtotal</th><th class="p5-r">Stock hoy</th></tr></thead>
                <tbody>
                @foreach($p->detalles as $d)
                    <tr>
                        <td><b>{{ $d->producto->pro_nombre ?? 'Producto eliminado' }}</b><div class="p5-mu">{{ $d->producto->pro_codigo ?? '' }}</div></td>
                        <td class="p5-r">{{ rtrim(rtrim(number_format((float) $d->dpr_cantidad, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="p5-r">{{ $fmt($d->dpr_precio) }}
                            @if(isset($cambios[$d->pro_id]))<div class="p5-mu" style="color:#d97706">hoy: {{ $fmt($cambios[$d->pro_id]) }}</div>@endif</td>
                        <td class="p5-r">{{ $fmt($d->dpr_subtotal) }}</td>
                        <td class="p5-r">
                            @if($d->producto)
                                {{ $d->producto->pro_stockactual }}
                                @if($p->estaAbierto() && $d->producto->pro_stockactual < $d->dpr_cantidad)<div class="p5-mu" style="color:#d97706">no alcanza</div>@endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($p->pre_condiciones || $p->pre_observacion)
        <div class="p5-card">
            @if($p->pre_condiciones)<div><span class="p5-label">Condiciones</span>{{ $p->pre_condiciones }}</div>@endif
            @if($p->pre_observacion)<div style="margin-top:10px"><span class="p5-label">Observación interna</span>{{ $p->pre_observacion }}</div>@endif
        </div>
    @endif

    @if($gestiona && $p->pre_estado !== 'FACTURADO')
        <div class="p5-card">
            <h3>Acciones</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                @if($p->pre_estado === 'BORRADOR')
                    <form method="POST" action="{{ route('presupuestos.estado', $p->pre_id) }}">@csrf<input type="hidden" name="estado" value="ENVIADO"><button class="p5-btn ghost">Marcar como enviado</button></form>
                @endif
                @if(in_array($p->pre_estado, ['BORRADOR', 'ENVIADO'], true))
                    <form method="POST" action="{{ route('presupuestos.estado', $p->pre_id) }}">@csrf<input type="hidden" name="estado" value="ACEPTADO"><button class="p5-btn">El cliente aceptó</button></form>
                @endif
                @if($p->pre_estado === 'RECHAZADO')
                    <form method="POST" action="{{ route('presupuestos.estado', $p->pre_id) }}">@csrf<input type="hidden" name="estado" value="BORRADOR"><button class="p5-btn ghost">Reabrir como borrador</button></form>
                @endif
                @if($p->estaAbierto())
                    <form method="POST" action="{{ route('presupuestos.estado', $p->pre_id) }}" style="display:flex;gap:6px" onsubmit="return confirm('¿Marcar el presupuesto como rechazado?')">
                        @csrf<input type="hidden" name="estado" value="RECHAZADO">
                        <input type="text" name="motivo" maxlength="200" class="p5-in" placeholder="Motivo (opcional)" style="width:200px">
                        <button class="p5-btn danger">Rechazar</button>
                    </form>
                    <form method="POST" action="{{ route('presupuestos.renovar', $p->pre_id) }}" style="display:flex;gap:6px;align-items:center">
                        @csrf
                        <input type="number" name="validez_dias" min="1" max="{{ config('presupuestos.validez_maxima_dias', 90) }}" value="{{ config('presupuestos.validez_defecto_dias', 7) }}" class="p5-in" style="width:80px">
                        <button class="p5-btn ghost">Renovar validez (días desde hoy)</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
