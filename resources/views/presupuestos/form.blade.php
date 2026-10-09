@extends('layouts.admin')

@section('contenido')
@include('presupuestos._estilos')
@php
    $previos = old('items', $p ? $p->detalles->map(fn ($d) => ['pro_id' => $d->pro_id, 'cantidad' => (float) $d->dpr_cantidad])->values()->all() : []);
    $validezInicial = (int) old('validez_dias', $p->pre_validez_dias ?? $validezDefecto);
    $cliInicial = old('cli_id', $p->cli_id ?? '');
@endphp
<script>
function presupuestoForm(productos, clientes, previos, cliInicial, validezInicial) {
    return {
        productos, clientes, lineas: [], buscar: '', buscarCli: '', abreCli: false,
        cliId: cliInicial ? Number(cliInicial) : null, validez: validezInicial,
        init() {
            (previos || []).forEach(l => {
                const p = this.productos.find(x => x.id == l.pro_id);
                if (p) this.lineas.push({ id: p.id, nombre: p.nombre, codigo: p.codigo, stock: p.stock, cantidad: l.cantidad });
            });
            if (this.cliId) { const c = this.clientes.find(x => x.id == this.cliId); if (c) this.buscarCli = c.nombre; }
        },
        get cliente() { return this.clientes.find(c => c.id == this.cliId) || null; },
        get mayorista() { return !!(this.cliente && this.cliente.mayorista); },
        get clientesFiltrados() {
            const q = this.buscarCli.trim().toLowerCase();
            if (!q || this.cliente) return this.clientes.slice(0, 8);
            return this.clientes.filter(c => c.nombre.toLowerCase().includes(q) || String(c.ruc || '').toLowerCase().includes(q)).slice(0, 8);
        },
        elegirCliente(c) { this.cliId = c.id; this.buscarCli = c.nombre; this.abreCli = false; },
        quitarCliente() { this.cliId = null; this.buscarCli = ''; },
        get resultados() {
            const q = this.buscar.trim().toLowerCase();
            if (!q) return [];
            return this.productos.filter(p => (p.nombre || '').toLowerCase().includes(q) || (p.codigo || '').toLowerCase().includes(q)).slice(0, 8);
        },
        precio(l) { const p = this.productos.find(x => x.id == l.id); if (!p) return 0; return this.mayorista && p.precio_may > 0 ? p.precio_may : p.precio; },
        agregar(p) {
            const ex = this.lineas.find(l => l.id == p.id);
            if (ex) { ex.cantidad = Number(ex.cantidad) + 1; }
            else { this.lineas.push({ id: p.id, nombre: p.nombre, codigo: p.codigo, stock: p.stock, cantidad: 1 }); }
            this.buscar = '';
        },
        quitar(i) { this.lineas.splice(i, 1); },
        subtotal(l) { return (Number(l.cantidad) || 0) * this.precio(l); },
        get total() { return this.lineas.reduce((a, l) => a + this.subtotal(l), 0); },
        fmt(n) { return Math.round(n).toLocaleString('es-PY'); },
        get vence() {
            const d = new Date(); d.setDate(d.getDate() + (Number(this.validez) || 0));
            return d.toLocaleDateString('es-PY');
        }
    };
}
</script>
<div class="p5" x-data="presupuestoForm(@js($productos), @js($clientes), @js($previos), @js($cliInicial), @js($validezInicial))">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">{{ $p ? 'Editar '.$p->numero : 'Nuevo presupuesto' }}</h2>
            <p class="p5-sub">Los precios los calcula el sistema con las reglas del punto de venta (mayorista y promociones). No se descuenta stock.</p>
        </div>
        <a class="p5-btn ghost" href="{{ $p ? route('presupuestos.show', $p->pre_id) : route('presupuestos.index') }}">Volver</a>
    </div>

    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ $p ? route('presupuestos.update', $p->pre_id) : route('presupuestos.store') }}"
          @submit="if (!lineas.length) { $event.preventDefault(); alert('Agregá al menos un producto.'); } else if (!cliId && !$refs.nombreLibre.value.trim()) { $event.preventDefault(); alert('Elegí un cliente o escribí a quién va dirigido.'); }">
        @csrf
        @if($p) @method('PUT') @endif
        <input type="hidden" name="cli_id" :value="cliId">

        <div class="p5-card">
            <h3>Cliente y validez</h3>
            <div class="p5-grid">
                <div style="position:relative">
                    <label class="p5-label">Cliente registrado</label>
                    <input type="text" x-model="buscarCli" class="p5-in" placeholder="Buscar por nombre o RUC" autocomplete="off"
                           @focus="abreCli = true" @click.away="abreCli = false" @input="cliId = null; abreCli = true">
                    <div class="p5-res" x-show="abreCli && !cliente && clientesFiltrados.length" x-cloak>
                        <template x-for="c in clientesFiltrados" :key="c.id">
                            <button type="button" @click="elegirCliente(c)"><b x-text="c.nombre"></b>
                                <span class="p5-mu" x-text="' · ' + (c.ruc || '') + (c.mayorista ? ' · mayorista' : '')"></span></button>
                        </template>
                    </div>
                    <span class="p5-mu" x-show="cliente && mayorista">Cliente mayorista: se usa el precio mayorista.</span>
                </div>
                <div x-show="!cliId">
                    <label class="p5-label">O nombre de quien no es cliente</label>
                    <input type="text" x-ref="nombreLibre" name="cliente_nombre" value="{{ old('cliente_nombre', $p->pre_cliente_nombre ?? '') }}" maxlength="120" class="p5-in" placeholder="Ej.: Juan Pérez">
                </div>
                <div>
                    <label class="p5-label">Validez (días) *</label>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <div class="p5-seg">
                            <button type="button" :class="validez == 7 ? 'on' : ''" @click="validez = 7">7</button>
                            <button type="button" :class="validez == 15 ? 'on' : ''" @click="validez = 15">15</button>
                            <button type="button" :class="validez == 30 ? 'on' : ''" @click="validez = 30">30</button>
                        </div>
                        <input type="number" name="validez_dias" x-model="validez" min="1" max="{{ $validezMax }}" class="p5-in" style="width:90px" required>
                    </div>
                    <span class="p5-mu">{{ $p ? 'Se cuenta desde el día en que se armó.' : 'Vale hasta el' }} <b x-show="{{ $p ? 'false' : 'true' }}" x-text="vence"></b></span>
                </div>
            </div>
            <div style="margin-top:12px"><label class="p5-label">Condiciones (forma de pago, entrega, garantía)</label>
                <input type="text" name="condiciones" value="{{ old('condiciones', $p->pre_condiciones ?? '') }}" maxlength="500" class="p5-in"></div>
            <div style="margin-top:12px"><label class="p5-label">Observación interna</label>
                <input type="text" name="observacion" value="{{ old('observacion', $p->pre_observacion ?? '') }}" maxlength="255" class="p5-in"></div>
        </div>

        <div class="p5-card">
            <h3>Productos</h3>
            <div style="position:relative;max-width:520px;margin-bottom:12px">
                <input type="text" x-model="buscar" class="p5-in" placeholder="Buscar por nombre o código (Enter agrega el primero)"
                       @keydown.enter.prevent="if (resultados.length) agregar(resultados[0])" autocomplete="off">
                <div class="p5-res" x-show="resultados.length" x-cloak>
                    <template x-for="p in resultados" :key="p.id">
                        <button type="button" @click="agregar(p)"><b x-text="p.nombre"></b>
                            <span class="p5-mu" x-text="' · ' + (p.codigo || 'sin código') + ' · stock ' + p.stock + ' · Gs. ' + fmt(p.precio)"></span></button>
                    </template>
                </div>
            </div>
            <div class="p5-tw">
                <table class="p5-table">
                    <thead><tr><th>Producto</th><th class="p5-r">Stock hoy</th><th class="p5-r" style="width:120px">Cantidad</th><th class="p5-r">Precio</th><th class="p5-r">Subtotal</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(l, i) in lineas" :key="l.id">
                            <tr>
                                <td><b x-text="l.nombre"></b><div class="p5-mu" x-text="l.codigo"></div>
                                    <input type="hidden" :name="'items[' + i + '][pro_id]'" :value="l.id"></td>
                                <td class="p5-r"><span x-text="l.stock"></span>
                                    <div class="p5-mu" x-show="Number(l.cantidad) > l.stock" style="color:#d97706">no alcanza hoy</div></td>
                                <td class="p5-r"><input type="number" step="0.01" min="0.01" class="p5-in p5-r" :name="'items[' + i + '][cantidad]'" x-model="l.cantidad" required></td>
                                <td class="p5-r" x-text="'Gs. ' + fmt(precio(l))"></td>
                                <td class="p5-r"><b x-text="'Gs. ' + fmt(subtotal(l))"></b></td>
                                <td class="p5-r"><button type="button" class="p5-btn ghost sm" @click="quitar(i)">Quitar</button></td>
                            </tr>
                        </template>
                        <tr x-show="!lineas.length"><td colspan="6" class="p5-c p5-mu" style="padding:22px">Buscá un producto para agregarlo.</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px">
                <span class="p5-mu">El stock es solo informativo: no se reserva ni se descuenta.</span>
                <div style="text-align:right"><span class="p5-mu">Total</span><div class="p5-total" x-text="'Gs. ' + fmt(total)"></div></div>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="p5-btn ghost" href="{{ $p ? route('presupuestos.show', $p->pre_id) : route('presupuestos.index') }}">Cancelar</a>
            <button type="submit" class="p5-btn ok">{{ $p ? 'Guardar cambios' : 'Guardar presupuesto' }}</button>
        </div>
    </form>
</div>
@endsection
