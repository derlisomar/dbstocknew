@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Libro diario</h2>
        <p class="ct-sub">Todos los asientos en orden. Los automáticos se corrigen anulando la operación que los generó; los manuales se anulan con un contra-asiento.</p></div>
        @can('CONTABILIDAD_GESTIONAR')<a class="ct-btn" href="{{ route('contabilidad.asiento.nuevo') }}">+ Asiento manual</a>@endcan
    </div>
    @if(session('success'))<div class="ct-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ct-alert bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ct-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @if(count($sync['errores'] ?? []))<div class="ct-alert bad">Hay operaciones sin contabilizar: {{ $sync['errores'][0] }}</div>@endif
    @include('contabilidad._menu')

    <form method="GET" class="ct-card">
        <div class="ct-grid">
            <div><label class="ct-label">Desde</label><input type="date" name="desde" class="ct-in" value="{{ $desde }}"></div>
            <div><label class="ct-label">Hasta</label><input type="date" name="hasta" class="ct-in" value="{{ $hasta }}"></div>
            <div><label class="ct-label">Origen</label><select name="origen" class="ct-in"><option value="">Todos</option>
                @foreach($origenes as $o)<option value="{{ $o }}" @selected($origen === $o)>{{ ucfirst(strtolower(str_replace('_', ' ', $o))) }}</option>@endforeach</select></div>
            <div><label class="ct-label">Buscar (concepto o N°)</label><input name="q" class="ct-in" value="{{ request('q') }}" maxlength="60"></div>
        </div>
        <div style="margin-top:12px"><button class="ct-btn" type="submit">Filtrar</button></div>
    </form>

    @forelse($asientos as $a)
        <div class="ct-asi">
            <div class="ct-asi-h">
                <div><b>Asiento N° {{ $a->asi_numero }}</b> · {{ \Carbon\Carbon::parse($a->asi_fecha)->format('d/m/Y') }} · {{ $a->asi_glosa }}</div>
                <div style="display:flex;gap:6px;align-items:center">
                    <span class="ct-badge {{ $a->asi_origen === 'MANUAL' ? 'warn' : 'info' }}">{{ ucfirst(strtolower(str_replace('_', ' ', $a->asi_origen))) }}</span>
                    @if($a->asi_estado === 'ANULADO')<span class="ct-badge bad">Anulado</span>@endif
                    @if($a->asi_revierte_id)<span class="ct-badge mute">Contra-asiento</span>@endif
                </div>
            </div>
            <div class="ct-tw">
                <table class="ct-table">
                    <tbody>
                    @foreach($lineas[$a->asi_id] ?? [] as $l)
                        <tr>
                            <td style="width:90px;color:var(--ct-mu)">{{ $l->cue_codigo }}</td>
                            <td class="{{ $l->lin_haber > 0 ? 'ct-haber' : '' }}">{{ $l->cue_nombre }}@if($l->lin_detalle) <span class="ct-mu">· {{ $l->lin_detalle }}</span>@endif</td>
                            <td class="ct-num" style="width:130px">{{ $l->lin_debe > 0 ? $fmt($l->lin_debe) : '' }}</td>
                            <td class="ct-num" style="width:130px">{{ $l->lin_haber > 0 ? $fmt($l->lin_haber) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($puedeGestionar && $a->asi_origen === 'MANUAL' && $a->asi_estado !== 'ANULADO' && ! $a->asi_revierte_id)
                <form method="POST" action="{{ route('contabilidad.asiento.anular', $a->asi_id) }}" style="padding:8px 14px;display:flex;gap:8px;flex-wrap:wrap" onsubmit="return confirm('¿Anular este asiento con un contra-asiento?')">
                    @csrf
                    <input name="motivo" class="ct-in" style="max-width:320px" placeholder="Motivo de la anulación" maxlength="150" required>
                    <button class="ct-btn danger sm" type="submit">Anular</button>
                </form>
            @endif
        </div>
    @empty
        <div class="ct-card"><p class="ct-mu">No hay asientos en ese período.</p></div>
    @endforelse

    {{ $asientos->links() }}
</div>
@endsection
