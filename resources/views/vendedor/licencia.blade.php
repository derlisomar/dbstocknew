@extends('vendedor.layout')
@section('titulo', 'Licencia y pagos')
@section('contenido')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $clases = ['SIN_LICENCIA' => ['b-mu', 'Sin licencia'], 'ACTIVA' => ['b-ok', 'Al día'], 'POR_VENCER' => ['b-warn', 'Por vencer'], 'GRACIA' => ['b-warn', 'En gracia'], 'SOLO_LECTURA' => ['b-bad', 'Solo lectura']];
    [$cl, $tx] = $clases[$estado['estado']];
@endphp
<h1>Licencia y pagos</h1>
<p class="pv-sub">Si el negocio no paga: aviso antes de vencer, {{ $graciaDias }} días de gracia y después solo lectura (puede consultar todo, no registrar). Nunca se pierden datos.</p>

<div class="pv-card">
    <h2>Estado: <span class="badge {{ $cl }}">{{ $tx }}</span></h2>
    <div class="mu">
        Plan: <b>{{ $plan->plan_nombre ?? 'sin plan asignado' }}</b>
        @if($estado['tipo'] === 'PERPETUA') · licencia permanente
        @elseif($estado['vence']) · vence el <b>{{ \Carbon\Carbon::parse($estado['vence'])->format('d/m/Y') }}</b>@if($estado['dias'] !== null) ({{ $estado['dias'] >= 0 ? 'faltan '.$estado['dias'].' días' : 'venció hace '.abs($estado['dias']).' días' }})@endif
        @endif
    </div>
    @if($estado['mensaje'])<div class="mu" style="margin-top:6px">Mensaje que ve el negocio: «{{ $estado['mensaje'] }}»</div>@endif
</div>

<div class="pv-grid" style="align-items:start">
    <form method="POST" action="{{ route('vendedor.licencia.plan') }}" class="pv-card">
        @csrf
        <h2>Asignar plan</h2>
        <label class="l">Plan</label>
        <select class="in" name="plan_id" required>@foreach($planes as $pl)<option value="{{ $pl->plan_id }}" @selected(($plan->plan_id ?? null) == $pl->plan_id)>{{ $pl->plan_nombre }} — {{ $fmt($pl->plan_precio) }}</option>@endforeach</select>
        <p class="mu">Aplica la edición, los módulos y los límites del plan. No cambia el vencimiento: eso lo hace el primer pago.</p>
        <button class="btn" onclick="return confirm('Se aplicarán los módulos y límites del plan. ¿Continuar?')">Asignar plan</button>
    </form>

    <form method="POST" action="{{ route('vendedor.licencia.ajustes') }}" class="pv-card">
        @csrf
        <h2>Ajustes manuales</h2>
        <label class="l">Tipo</label>
        <select class="in" name="lic_tipo"><option value="SUSCRIPCION" @selected(($estado['tipo'] ?? 'SUSCRIPCION') !== 'PERPETUA')>Suscripción (vence)</option><option value="PERPETUA" @selected($estado['tipo'] === 'PERPETUA')>Permanente (pago único)</option></select>
        <label class="l" style="margin-top:10px">Vence el</label>
        <input class="in" type="date" name="lic_vence" value="{{ $estado['vence'] }}">
        <label class="l" style="margin-top:10px">Días de gracia</label>
        <input class="in" type="number" min="0" max="60" name="lic_gracia_dias" value="{{ $graciaDias }}" required>
        <label class="mu" style="display:block;margin:10px 0"><input type="checkbox" name="lic_suspendida" value="1" @checked($suspendida)> Suspender ahora (solo lectura inmediata)</label>
        <button class="btn">Guardar ajustes</button>
    </form>
</div>

<form method="POST" action="{{ route('vendedor.licencia.pagos') }}" class="pv-card">
    @csrf
    <h2>Registrar pago</h2>
    <div class="pv-grid">
        <div><label class="l">Fecha del pago</label><input class="in" type="date" name="fecha" value="{{ now(config('vendedor.zona'))->toDateString() }}"></div>
        <div><label class="l">Monto (Gs.) *</label><input class="in" type="number" min="0" step="1" name="monto" value="{{ old('monto', (int) ($plan->plan_precio ?? 0)) }}" required></div>
        <div><label class="l">Forma</label><select class="in" name="forma"><option>Efectivo</option><option>Transferencia</option><option>Cheque</option><option>Otro</option></select></div>
        <div><label class="l">Referencia</label><input class="in" name="referencia" maxlength="120" placeholder="Nº de transferencia o recibo"></div>
        <div><label class="l">Cubre (meses)</label><input class="in" type="number" min="0" max="60" name="meses" value="{{ $plan->plan_meses ?? 1 }}"><span class="mu">0 = pago único (permanente)</span></div>
        <div><label class="l">O hasta (fecha exacta)</label><input class="in" type="date" name="hasta"></div>
    </div>
    <div style="margin-top:12px"><label class="l">Nota</label><input class="in" name="nota" maxlength="255"></div>
    <p class="mu">Si paga dentro de la gracia, el período sigue desde el vencimiento anterior (no pierde días). Si ya pasó la gracia, cuenta desde hoy. Al registrar el pago se levanta la suspensión.</p>
    <button class="btn ok">Registrar pago</button>
</form>

<div class="pv-card">
    <h2>Pagos registrados</h2>
    <div class="tw"><table>
        <thead><tr><th>Fecha</th><th>Plan</th><th class="r">Monto</th><th>Forma</th><th>Período</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($pagos as $pg)
            <tr><td>{{ $pg->lpa_fecha->format('d/m/Y') }}</td><td>{{ $pg->plan->plan_nombre ?? '—' }}</td><td class="r">{{ $fmt($pg->lpa_monto) }}</td>
                <td>{{ $pg->lpa_forma }}<div class="mu">{{ $pg->lpa_referencia }}</div></td>
                <td>{{ $pg->lpa_hasta ? ($pg->lpa_desde ? $pg->lpa_desde->format('d/m/Y').' → ' : '').$pg->lpa_hasta->format('d/m/Y') : 'Pago único' }}</td>
                <td><span class="badge {{ $pg->lpa_estado === 'ACTIVO' ? 'b-ok' : 'b-bad' }}">{{ $pg->lpa_estado === 'ACTIVO' ? 'Activo' : 'Anulado' }}</span>@if($pg->lpa_motivo_anulacion)<div class="mu">{{ $pg->lpa_motivo_anulacion }}</div>@endif</td>
                <td class="r">@if($pg->lpa_id === (int) $ultimoActivo)
                    <form method="POST" action="{{ route('vendedor.licencia.pagos.anular', $pg->lpa_id) }}" style="display:flex;gap:6px" onsubmit="return confirm('¿Anular este pago y devolver el vencimiento anterior?')">@csrf
                        <input class="in" name="motivo" placeholder="Motivo" required maxlength="200" style="width:150px"><button class="btn r sm">Anular</button></form>@endif</td></tr>
        @empty<tr><td colspan="7" class="mu">Todavía no hay pagos registrados.</td></tr>@endforelse
        </tbody></table></div>
</div>
@endsection
