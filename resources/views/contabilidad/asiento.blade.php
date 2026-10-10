@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $lineasIniciales = old('lineas', [['cue_id' => '', 'debe' => '', 'haber' => '', 'detalle' => ''], ['cue_id' => '', 'debe' => '', 'haber' => '', 'detalle' => '']]);
@endphp
<div class="ct" x-data="asientoForm(@js($lineasIniciales), @js($claves))">
    <div class="ct-head"><div><h2 class="ct-title">Asiento manual</h2>
        <p class="ct-sub">Para ajustes que el sistema no puede deducir solo. Tiene que cuadrar (debe = haber) y queda a tu nombre en la auditoría.@if($cerradoHasta) Períodos cerrados hasta el {{ \Carbon\Carbon::parse($cerradoHasta)->format('d/m/Y') }}.@endif</p></div></div>
    @if(session('error'))<div class="ct-alert bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ct-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @include('contabilidad._menu')

    <div class="ct-card">
        <h3>Plantillas frecuentes</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="ct-btn ghost sm" @click="plantilla('merma')">Faltante de inventario (merma, robo, vencido)</button>
            <button type="button" class="ct-btn ghost sm" @click="plantilla('cajaFalta')">Faltante de caja</button>
            <button type="button" class="ct-btn ghost sm" @click="plantilla('cajaSobra')">Sobrante de caja</button>
            <button type="button" class="ct-btn ghost sm" @click="plantilla('apertura')">Apertura (saldos iniciales)</button>
        </div>
        <p class="ct-hint">Los faltantes de inventario y de caja que el sistema detecta (conteos, ajustes y cierres de caja) ya se contabilizan solos. Usá estas plantillas para lo que se descubre fuera del sistema. Inventario según el stock: <b>{{ $fmt($inventario) }}</b>.</p>
    </div>

    <form method="POST" action="{{ route('contabilidad.asiento.guardar') }}" class="ct-card">
        @csrf
        <div class="ct-grid" style="margin-bottom:14px">
            <div><label class="ct-label">Fecha</label><input type="date" name="fecha" x-model="fecha" class="ct-in" max="{{ now()->toDateString() }}" required></div>
            <div style="grid-column:span 2"><label class="ct-label">Concepto / motivo</label><input name="glosa" x-model="glosa" class="ct-in" maxlength="255" placeholder="Ej.: Faltante detectado en el conteo del 05/10" required></div>
        </div>

        <template x-for="(l, i) in lineas" :key="i">
            <div class="ct-rowx">
                <select :name="'lineas['+i+'][cue_id]'" x-model="l.cue_id" class="ct-in">
                    <option value="">Elegí la cuenta…</option>
                    @foreach($cuentas as $c)<option value="{{ $c->cue_id }}">{{ $c->cue_codigo }} · {{ $c->cue_nombre }}</option>@endforeach
                </select>
                <input :name="'lineas['+i+'][debe]'" x-model="l.debe" @input="if(l.debe){l.haber=''}" type="number" step="0.01" min="0" class="ct-in" placeholder="Debe">
                <input :name="'lineas['+i+'][haber]'" x-model="l.haber" @input="if(l.haber){l.debe=''}" type="number" step="0.01" min="0" class="ct-in" placeholder="Haber">
                <input :name="'lineas['+i+'][detalle]'" x-model="l.detalle" class="ct-in" maxlength="160" placeholder="Detalle (opcional)">
                <button type="button" class="ct-btn ghost sm" @click="quitar(i)" x-show="lineas.length > 2" title="Quitar">✕</button>
            </div>
        </template>

        <button type="button" class="ct-btn ghost sm" @click="agregar()">+ Agregar línea</button>

        <div style="margin-top:14px;display:flex;gap:18px;flex-wrap:wrap;align-items:center">
            <div>Debe: <b x-text="fmt(totalDebe())"></b></div>
            <div>Haber: <b x-text="fmt(totalHaber())"></b></div>
            <span class="ct-badge" :class="cuadra() ? 'ok' : 'bad'" x-text="cuadra() ? 'Cuadra' : 'No cuadra: diferencia ' + fmt(Math.abs(totalDebe() - totalHaber()))"></span>
        </div>
        <div style="margin-top:14px"><button class="ct-btn ok" type="submit" :disabled="!cuadra()">Guardar asiento</button></div>
    </form>
</div>

<script>
function asientoForm(iniciales, claves) {
    return {
        fecha: '{{ old('fecha', now()->toDateString()) }}',
        glosa: @js(old('glosa', '')),
        lineas: iniciales,
        inventario: {{ (float) $inventario }},
        agregar() { this.lineas.push({cue_id: '', debe: '', haber: '', detalle: ''}); },
        quitar(i) { this.lineas.splice(i, 1); },
        num(v) { return parseFloat(v) || 0; },
        totalDebe() { return this.lineas.reduce((s, l) => s + this.num(l.debe), 0); },
        totalHaber() { return this.lineas.reduce((s, l) => s + this.num(l.haber), 0); },
        cuadra() { return this.totalDebe() > 0 && Math.abs(this.totalDebe() - this.totalHaber()) < 0.005; },
        fmt(n) { return 'Gs. ' + Math.round(n).toLocaleString('es-PY'); },
        plantilla(t) {
            const c = (k) => String(claves[k] ?? '');
            const L = (k, d, h, det) => ({cue_id: c(k), debe: d, haber: h, detalle: det || ''});
            if (t === 'merma') {
                this.glosa = 'Faltante de inventario (merma, robo o vencimiento): ';
                this.lineas = [L('faltante_inventario', '', '', 'Pérdida'), L('mercaderias', '', '', 'Baja de mercadería')];
            } else if (t === 'cajaFalta') {
                this.glosa = 'Faltante de caja: ';
                this.lineas = [L('faltante_caja', '', '', 'Faltante'), L('caja_gs', '', '', 'Caja')];
            } else if (t === 'cajaSobra') {
                this.glosa = 'Sobrante de caja: ';
                this.lineas = [L('caja_gs', '', '', 'Caja'), L('sobrante_caja', '', '', 'Sobrante')];
            } else {
                this.glosa = 'Asiento de apertura: saldos iniciales';
                this.lineas = [L('caja_gs', '', '', 'Efectivo inicial'), L('banco', '', '', 'Saldo en bancos'), L('clientes', '', '', 'Deudas de clientes'),
                    L('mercaderias', this.inventario || '', '', 'Valor del inventario'), L('proveedores', '', '', 'Deudas con proveedores'), L('capital', '', '', 'Capital (diferencia)')];
            }
        },
    };
}
</script>
@endsection
