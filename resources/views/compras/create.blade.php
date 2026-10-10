@extends('layouts.admin')

@section('contenido')
@include('compras._estilos')
<script>
function compraForm(productos, previos, tipoInicial) {
    return {
        productos: productos, tipo: tipoInicial, buscar: '', lineas: [],
        init() {
            (previos || []).forEach(l => {
                const p = this.productos.find(x => x.id == l.pro_id);
                if (p) this.lineas.push({ id: p.id, nombre: p.nombre, codigo: p.codigo, stock: p.stock, cantidad: l.cantidad, costo: l.costo });
            });
        },
        get resultados() {
            const q = this.buscar.trim().toLowerCase();
            if (!q) return [];
            return this.productos.filter(p => (p.nombre || '').toLowerCase().includes(q) || (p.codigo || '').toLowerCase().includes(q)).slice(0, 8);
        },
        agregar(p) {
            const ex = this.lineas.find(l => l.id == p.id);
            if (ex) { ex.cantidad = Number(ex.cantidad) + 1; }
            else { this.lineas.push({ id: p.id, nombre: p.nombre, codigo: p.codigo, stock: p.stock, cantidad: 1, costo: p.costo }); }
            this.buscar = '';
        },
        quitar(i) { this.lineas.splice(i, 1); },
        subtotal(l) { return (Number(l.cantidad) || 0) * (Number(l.costo) || 0); },
        get total() { return this.lineas.reduce((a, l) => a + this.subtotal(l), 0); },
        fmt(n) { return Math.round(n).toLocaleString('es-PY'); }
    };
}
</script>
<div class="p5" x-data="compraForm(@js($productos), @js(old('items', [])), @js(old('tipo', 'CREDITO')))">
    <div class="p5-head">
        <div>
            <h2 class="p5-title">Registrar compra</h2>
            <p class="p5-sub">Al guardar entra el stock, se actualiza el costo de cada producto y se genera la cuenta a pagar.</p>
        </div>
        <a class="p5-btn ghost" href="{{ route('compras.index') }}">Volver al listado</a>
    </div>

    @if($errors->any())<div class="p5-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ route('compras.store') }}" @submit="if (!lineas.length) { $event.preventDefault(); alert('Agregá al menos un producto.'); }">
        @csrf
        <div class="p5-card">
            <h3>Datos de la compra</h3>
            <div class="p5-grid">
                <div><label class="p5-label">Proveedor *</label>
                    <select name="prov_id" class="p5-in" required>
                        <option value="">Elegí un proveedor</option>
                        @foreach($proveedores as $p)<option value="{{ $p->prov_id }}" @selected((string) old('prov_id') === (string) $p->prov_id)>{{ $p->prov_razonsocial }}</option>@endforeach
                    </select></div>
                <div><label class="p5-label">Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="p5-in"></div>
                <div><label class="p5-label">Nº de factura / remisión</label><input type="text" name="nro_documento" value="{{ old('nro_documento') }}" maxlength="40" class="p5-in"></div>
                <div><label class="p5-label">Timbrado del proveedor</label><input type="text" name="timbrado" value="{{ old('timbrado') }}" maxlength="20" class="p5-in" placeholder="Opcional (libro IVA)"></div>
                <div><label class="p5-label">Condición *</label>
                    <select name="tipo" x-model="tipo" class="p5-in" required>
                        <option value="CREDITO">A crédito (queda deuda)</option>
                        <option value="CONTADO">Al contado (se paga ahora)</option>
                    </select></div>
                <div x-show="tipo === 'CREDITO'"><label class="p5-label">Vence el</label>
                    <input type="date" name="vencimiento" value="{{ old('vencimiento', now()->addDays($plazo)->toDateString()) }}" class="p5-in"></div>
                <div x-show="tipo === 'CONTADO'"><label class="p5-label">Forma de pago</label>
                    <select name="forma_pago" class="p5-in">
                        @foreach($formas as $f)<option value="{{ $f }}" @selected(old('forma_pago', 'EFECTIVO') === $f)>{{ ucfirst(strtolower($f)) }}</option>@endforeach
                    </select>
                    <span class="p5-mu">En efectivo sale de tu caja abierta.</span></div>
                <div x-show="tipo === 'CONTADO'"><label class="p5-label">Referencia (opcional)</label>
                    <input type="text" name="referencia" value="{{ old('referencia') }}" maxlength="120" class="p5-in" placeholder="Nº de transferencia o cheque"></div>
            </div>
            <div style="margin-top:12px"><label class="p5-label">Observación</label>
                <input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="255" class="p5-in"></div>
        </div>

        <div class="p5-card">
            <h3>Productos</h3>
            <div style="position:relative;max-width:520px;margin-bottom:12px">
                <input type="text" x-model="buscar" class="p5-in" placeholder="Buscar por nombre o código (Enter agrega el primero)"
                       @keydown.enter.prevent="if (resultados.length) agregar(resultados[0])" autocomplete="off">
                <div class="p5-res" x-show="resultados.length" x-cloak>
                    <template x-for="p in resultados" :key="p.id">
                        <button type="button" @click="agregar(p)">
                            <b x-text="p.nombre"></b>
                            <span class="p5-mu" x-text="' · ' + (p.codigo || 'sin código') + ' · stock ' + p.stock + ' · costo ' + fmt(p.costo)"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="p5-tw">
                <table class="p5-table">
                    <thead><tr><th>Producto</th><th class="p5-r">Stock actual</th><th class="p5-r" style="width:120px">Cantidad</th><th class="p5-r" style="width:150px">Costo unitario</th><th class="p5-r">Subtotal</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(l, i) in lineas" :key="l.id">
                            <tr>
                                <td><b x-text="l.nombre"></b><div class="p5-mu" x-text="l.codigo"></div>
                                    <input type="hidden" :name="'items[' + i + '][pro_id]'" :value="l.id"></td>
                                <td class="p5-r" x-text="l.stock"></td>
                                <td class="p5-r"><input type="number" step="0.01" min="0.01" class="p5-in p5-r" :name="'items[' + i + '][cantidad]'" x-model="l.cantidad" required></td>
                                <td class="p5-r"><input type="number" step="0.01" min="0" class="p5-in p5-r" :name="'items[' + i + '][costo]'" x-model="l.costo" required></td>
                                <td class="p5-r"><b x-text="'Gs. ' + fmt(subtotal(l))"></b></td>
                                <td class="p5-r"><button type="button" class="p5-btn ghost sm" @click="quitar(i)">Quitar</button></td>
                            </tr>
                        </template>
                        <tr x-show="!lineas.length"><td colspan="6" class="p5-c p5-mu" style="padding:22px">Buscá un producto para agregarlo a la compra.</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px">
                <span class="p5-mu">El costo del producto se recalcula {{ config('compras.costo') === 'ultimo' ? 'con el último precio pagado' : 'como promedio ponderado entre el stock actual y esta compra' }}.</span>
                <div style="text-align:right"><span class="p5-mu">Total de la compra</span><div class="p5-total" x-text="'Gs. ' + fmt(total)"></div></div>
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="p5-btn ghost" href="{{ route('compras.index') }}">Cancelar</a>
            <button type="submit" class="p5-btn ok">Guardar compra</button>
        </div>
    </form>
</div>
@endsection
