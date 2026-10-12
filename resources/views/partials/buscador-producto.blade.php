{{-- Selector de producto con búsqueda al tipear (nombre, código o código de barra).
     Variables: $name, $lista (colección de productos con ->codigos), $valor (id elegido), $requerido, $todos (permite "Todos"), $campo --}}
@php
    $items = $lista->map(fn ($p) => [
        'id' => $p->pro_id,
        'texto' => $p->pro_codigo.' - '.$p->pro_nombre.' (stock '.rtrim(rtrim(number_format((float) $p->pro_stockactual, 3, '.', ''), '0'), '.').')',
        'buscar' => mb_strtolower($p->pro_codigo.' '.$p->pro_nombre.' '.$p->codigos->pluck('cod_codigo')->implode(' ')),
    ])->values();
@endphp
<div class="relative" x-data="{
        items: @js($items), abierto: false, q: '', sel: @js($valor !== null && $valor !== '' ? (int) $valor : null), pos: 0,
        get elegido() { return this.items.find(i => i.id === this.sel) },
        get lista() { const t = this.q.trim().toLowerCase(); const r = t ? this.items.filter(i => t.split(/\s+/).every(w => i.buscar.includes(w))) : this.items; return r.slice(0, 50) },
        elegir(i) { this.sel = i ? i.id : null; this.q = ''; this.abierto = false },
        abrir() { const r = this.$refs.btn.getBoundingClientRect(); this.caja = `top:${r.bottom + 4}px;left:${r.left}px;width:${Math.max(r.width, 352)}px`; this.abierto = true; this.pos = 0; this.$nextTick(() => this.$refs.q.focus()) },
        caja: '',
        enter() { const i = this.lista[this.pos]; if (i) this.elegir(i) },
    }" @click.window="if (abierto && !$refs.btn.contains($event.target) && !$refs.menu.contains($event.target)) abierto = false" @keydown.escape.window="abierto = false" @scroll.window.passive="abierto = false" @resize.window="abierto = false">
    <input type="hidden" name="{{ $name }}" :value="sel ?? ''" @if(!empty($requerido)) required @endif>
    <button type="button" x-ref="btn" @click="abrir()" class="{{ $campo }} w-full text-left truncate flex justify-between items-center">
        <span x-text="elegido ? elegido.texto : '{{ ! empty($todos) ? 'Todos' : 'Elegí un producto' }}'" class="truncate"></span>
        <span class="text-gray-400 ml-2">▾</span>
    </button>
    <template x-teleport="body"><div x-ref="menu" x-show="abierto" x-cloak :style="caja" style="display:none" class="fixed z-[9999] rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl">
        <input x-ref="q" x-model="q" type="text" placeholder="Escribí nombre, código o código de barra..." autocomplete="off"
               @input="pos = 0"
               @keydown.enter.prevent="enter()"
               @keydown.arrow-down.prevent="pos = Math.min(pos + 1, lista.length - 1)"
               @keydown.arrow-up.prevent="pos = Math.max(pos - 1, 0)"
               @keydown.escape="abierto = false"
               class="w-full rounded-t-lg border-0 border-b border-gray-200 dark:border-gray-700 bg-transparent py-2 px-3 text-sm text-gray-800 dark:text-white outline-none">
        <ul class="max-h-64 overflow-y-auto text-sm">
            @if(! empty($todos))
                <li @click="elegir(null)" class="px-3 py-2 cursor-pointer text-gray-500 hover:bg-blue-50 dark:hover:bg-gray-800">Todos</li>
            @endif
            <template x-for="(i, n) in lista" :key="i.id">
                <li @click="elegir(i)" @mouseenter="pos = n" :class="n === pos ? 'bg-blue-600 text-white' : 'text-gray-800 dark:text-gray-200'" class="px-3 py-2 cursor-pointer" x-text="i.texto"></li>
            </template>
            <li x-show="lista.length === 0" class="px-3 py-2 text-gray-400">Sin resultados</li>
        </ul>
    </div></template>
</div>
