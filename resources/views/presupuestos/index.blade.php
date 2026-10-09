@extends('layouts.admin')

@section('contenido')
@include('presupuestos._estilos')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $badge = ['BORRADOR' => ['mute', 'Borrador'], 'ENVIADO' => ['info', 'Enviado'], 'ACEPTADO' => ['ok', 'Aceptado'],
              'RECHAZADO' => ['bad', 'Rechazado'], 'FACTURADO' => ['ok', 'Vendido'], 'VENCIDO' => ['bad', 'Vencido']];
    $tabs = [
        'vigentes' => ['Vigentes', 't-info', 'Abiertos y dentro de su plazo'],
        'por_vender' => ['Por vender', 't-ok', 'Aceptados, listos para vender'],
        'vencidos' => ['Vencidos', 't-bad', 'Pasó su fecha de validez'],
        'facturados' => ['Vendidos', 't-ok', 'Ya convertidos en venta'],
        'rechazados' => ['Rechazados', 't-warn', ''],
        'todos' => ['Todos', '', ''],
    ];
@endphp
<div class="p5">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">Presupuestos</h2>
            <p class="p5-sub">Un presupuesto no descuenta stock: eso pasa recién cuando se convierte en venta.</p>
        </div>
        @can('PRESUPUESTOS_GESTIONAR')<a class="p5-btn" href="{{ route('presupuestos.create') }}">+ Nuevo presupuesto</a>@endcan
    </div>

    @if(session('success'))<div class="p5-alert ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="p5-tabs">
        @foreach($tabs as $clave => [$titulo, $clase, $ayuda])
            <a class="p5-tab {{ $clase }} {{ $vista === $clave ? 'on' : '' }}" title="{{ $ayuda }}"
               href="{{ route('presupuestos.index', array_merge(request()->except('page', 'vista'), ['vista' => $clave])) }}">
                <span class="p5-mu">{{ $titulo }}</span>
                <b>{{ $resumen[$clave]['cantidad'] }}</b>
                <span class="p5-mu">{{ $fmt($resumen[$clave]['monto']) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('presupuestos.index') }}" class="p5-card">
        <input type="hidden" name="vista" value="{{ $vista }}">
        <div class="p5-grid">
            <div><label class="p5-label">Cliente o Nº</label><input type="text" name="q" value="{{ request('q') }}" maxlength="60" class="p5-in" placeholder="Nombre, RUC o número"></div>
            <div><label class="p5-label">Emitido desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="p5-in"></div>
            <div><label class="p5-label">Emitido hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="p5-in"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px">
            <button class="p5-btn" type="submit">Filtrar</button>
            @if(request('q') || request('desde') || request('hasta'))<a class="p5-btn ghost" href="{{ route('presupuestos.index', ['vista' => $vista]) }}">Limpiar</a>@endif
        </div>
    </form>

    <div class="p5-card" style="padding:0">
        <div class="p5-tw">
            <table class="p5-table">
                <thead><tr>
                    <th>Nº</th><th>Emitido</th><th>Cliente</th><th class="p5-r">Total</th><th>Vale hasta</th><th>Estado</th><th></th>
                </tr></thead>
                <tbody>
                @forelse($presupuestos as $p)
                    @php
                        $ver = $p->estadoVisible();
                        [$clase, $texto] = $badge[$ver];
                        $dias = $p->diasRestantes();
                    @endphp
                    <tr class="{{ $ver === 'VENCIDO' ? 'p5-row-venc' : '' }}">
                        <td><b>{{ $p->numero }}</b></td>
                        <td>{{ $p->pre_fecha->setTimezone(config('presupuestos.zona'))->format('d/m/Y') }}</td>
                        <td>{{ $p->nombre_cliente }}</td>
                        <td class="p5-r">{{ $fmt($p->pre_total) }}</td>
                        <td>
                            {{ $p->pre_fecha_vencimiento->format('d/m/Y') }}
                            @if($p->estaAbierto())
                                <div class="p5-mu">
                                    @if($dias < 0) venció hace {{ abs($dias) }} {{ abs($dias) === 1 ? 'día' : 'días' }}
                                    @elseif($dias === 0) vence hoy
                                    @elseif($dias <= $avisoDias) <b style="color:#d97706">vence en {{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}</b>
                                    @else faltan {{ $dias }} días @endif
                                </div>
                            @endif
                        </td>
                        <td><span class="p5-badge {{ $clase }}">{{ $texto }}</span>
                            @if($ver === 'VENCIDO' && $p->pre_estado === 'ACEPTADO')<div class="p5-mu">estaba aceptado</div>@endif</td>
                        <td class="p5-r" style="white-space:nowrap">
                            <a class="p5-btn ghost sm" href="{{ route('presupuestos.show', $p->pre_id) }}">Ver</a>
                            @can('PDV_USAR')
                                @if($p->estaAbierto() && ! $p->estaVencido())
                                    <a class="p5-btn ok sm" href="{{ route('pdv.index', ['presupuesto' => $p->pre_id]) }}">Vender</a>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p5-c p5-mu" style="padding:28px">No hay presupuestos en esta lista.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px">{{ $presupuestos->links() }}</div>
    </div>
</div>
@endsection
