@extends('layouts.admin')

@section('contenido')

<div x-data="pdvApp()" class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-6rem)] relative">
    
    <!-- SECCIÓN IZQUIERDA: Búsqueda de Productos y Carrito (Ocupa 8 columnas) -->
    <!-- ================= SECCIÓN IZQUIERDA: Buscador Rápido y Carrito ================= -->
    <div class="xl:col-span-8 flex flex-col gap-4 h-full">
        
        <!-- Buscador Interactivo de Productos (Estilo Dropdown) -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-4 relative" x-data="{ openBusquedaProd: false }">
            <label class="block text-[11px] uppercase tracking-wider font-black text-gray-500 dark:text-gray-400 mb-2">
                Agregar Producto (Escáner o Nombre)
            </label>
            <div class="relative w-full">
                <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                
                <!-- Input principal de búsqueda -->
                <input 
                    type="text" 
                    x-model="searchProduct" 
                    @focus="openBusquedaProd = true" 
                    @click.away="openBusquedaProd = false"
                    @input="openBusquedaProd = true"
                    placeholder="Escanee el código de barras o busque por nombre..." 
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 py-3.5 pl-12 pr-4 text-sm font-medium text-gray-800 dark:text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all shadow-sm"
                    autofocus
                >
                
                <!-- Lista desplegable flotante de productos -->
                <div 
                    x-show="openBusquedaProd && searchProduct.length > 0" 
                    x-transition
                    class="absolute z-50 w-full mt-2 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-[400px] overflow-y-auto custom-scrollbar"
                    style="display: none;"
                >
                    <template x-for="pro in filteredProducts" :key="pro.pro_id">
                        <div 
                            @click="agregarAlCarrito(pro); searchProduct = ''; openBusquedaProd = false;"
                            class="px-5 py-3 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/30 border-b border-gray-50 dark:border-gray-800 last:border-0 transition-colors flex justify-between items-center group"
                            :class="pro.pro_stockactual <= 0 ? 'opacity-70 bg-red-50/30 dark:bg-red-900/10' : ''"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-200 dark:border-gray-600 shrink-0">
                                    <img x-show="pro.pro_imagen" :src="'/storage/' + pro.pro_imagen" class="w-full h-full object-cover">
                                    <span x-show="!pro.pro_imagen" class="text-gray-400 text-lg">📦</span>
                                </div>
                                <div>
                                    <div class="text-xs font-black text-gray-400 dark:text-gray-500 mb-0.5 tracking-wider uppercase" x-text="pro.pro_codigo"></div>
                                    <div class="text-sm font-bold text-gray-800 dark:text-white line-clamp-1" x-text="pro.pro_nombre"></div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-black text-blue-600 dark:text-blue-400 mb-1" x-text="formatMoneda(pro.pro_precioventa)"></div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md inline-block uppercase tracking-wider" 
                                      :class="pro.pro_stockactual <= 0 ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-700'"
                                      x-text="'Stock: ' + pro.pro_stockactual"></span>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Mensaje si no hay resultados -->
                    <div x-show="filteredProducts.length === 0" class="px-5 py-8 text-sm text-gray-400 text-center font-medium flex flex-col items-center gap-2">
                        <span class="text-3xl">📭</span>
                        No se encontró ningún producto con ese código o nombre.
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel del Carrito (Ahora más amplio) -->
        <div class="bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm flex flex-col flex-1 overflow-hidden relative">
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 dark:bg-gray-900/80 text-gray-500 text-[11px] uppercase font-black tracking-wider sticky top-0 backdrop-blur-md z-10">
                        <tr>
                            <th class="py-4 px-5">Producto en Carrito</th>
                            <th class="py-4 px-5 text-center w-24">Cant.</th>
                            <th class="py-4 px-5 text-right w-32">Precio Unit.</th>
                            <th class="py-4 px-5 text-right w-32">Subtotal</th>
                            <th class="py-4 px-5 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                        <template x-for="(item, index) in carrito" :key="index">
                            <tr class="hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors group">
                                <td class="py-3 px-5 font-medium text-gray-800 dark:text-gray-200" x-text="item.pro_nombre"></td>
                                <td class="py-3 px-5">
                                    <div class="flex items-center justify-center">
                                        <input type="number" min="1" x-model.number="item.cantidad" @change="actualizarSubtotal(index)" class="w-16 text-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold">
                                    </div>
                                </td>
                                <td class="py-3 px-5 text-right text-gray-500 font-medium" x-text="formatMoneda(item.precio)"></td>
                                <td class="py-3 px-5 text-right font-black text-emerald-600 dark:text-emerald-400" x-text="formatMoneda(item.subtotal)"></td>
                                <td class="py-3 px-5 text-center">
                                    <button @click="eliminarDelCarrito(index)" class="text-gray-300 hover:text-red-500 transition-colors p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="Quitar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="carrito.length === 0">
                            <td colspan="5" class="py-20 text-center">
                                <div class="text-5xl mb-4 opacity-30">🛒</div>
                                <p class="text-gray-400 font-medium text-lg">El carrito está vacío.</p>
                                <p class="text-gray-500 text-sm mt-1">Utilice el buscador superior o el escáner para agregar productos.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DERECHA: Datos de Venta y Cobro (Ocupa 4 columnas) -->
    <div class="lg:col-span-4 bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-6 flex flex-col justify-between">
        
        <div class="space-y-6">
            <h3 class="text-base font-black text-gray-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span> Resumen de Venta
            </h3>
        
            <!-- CONTENEDOR FLEX: Agrupa el buscador y el botón para ponerlos lado a lado -->
            <div class="flex items-start gap-2">
                
                <!-- Buscador Interactivo de Clientes -->
                <div class="relative flex-1" x-data="{ openBusqueda: false }">
                    
                    <!-- Input visible para escribir y buscar -->
                    <input 
                        type="text" 
                        x-model="searchCliente" 
                        @focus="openBusqueda = true" 
                        @click.away="openBusqueda = false"
                        @input="clienteSeleccionado = null; cli_id = null; openBusqueda = true"
                        placeholder="Seleccione o busque un cliente..." 
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-4 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500 transition"
                    >

                    <!-- 👇 Indicador discreto (Tarifa Mayorista) -->
                    <div x-show="clienteSeleccionado && (clienteSeleccionado.tipo === 'MAYORISTA' || clienteSeleccionado.cli_tipo === 'MAYORISTA')" 
                        class="mt-1.5 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium px-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        <span>Tarifa Mayorista Activa</span>
                    </div>
                    
                    <!-- Input oculto que guarda el ID real del cliente para el carrito/venta -->
                    <input type="hidden" name="cli_id" x-model="cli_id">

                    <!-- Lista desplegable flotante -->
                    <div 
                        x-show="openBusqueda" 
                        x-transition
                        class="absolute z-50 w-full mt-1 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-60 overflow-y-auto"
                        style="display: none;"
                    >
                        <template x-for="cli in clientesFiltrados" :key="cli.cli_id">
                            <div 
                                @click="seleccionarCliente(cli); openBusqueda = false"
                                class="px-4 py-2.5 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/30 text-sm text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 last:border-0 transition-colors"
                            >
                                <span class="font-black text-gray-900 dark:text-white" x-text="cli.cli_ruc_ci"></span> - 
                                <span x-text="cli.cli_nombre + ' ' + (cli.cli_apellido || '')"></span>
                            </div>
                        </template>

                        <!-- Mensaje por si no hay coincidencias -->
                        <div x-show="clientesFiltrados.length === 0" class="px-4 py-3 text-sm text-gray-500 text-center font-medium">
                            No se encontraron clientes con esa búsqueda.
                        </div>
                    </div>
                </div>

                <!-- BOTÓN: Registrar Cliente (Ahora alineado al lado del buscador) -->
                <button @click="showModalCliente = true" type="button" class="flex-shrink-0 flex items-center justify-center w-[42px] h-[42px] bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow transition" title="Registrar Cliente Nuevo">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </button>

            </div>

            <!-- Configuraciones de Pago -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-2">Moneda</label>
                    <select x-model="moneda" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <option value="GS">Guaraníes (Gs.)</option>
                        <option value="USD">Dólares (USD)</option>
                        <option value="BRL">Reales (R$)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-2">Condición</label>
                    <select x-model="vta_tipo" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <option value="CONTADO">Contado</option>
                        <option value="CREDITO">Crédito</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-2">Método de Pago</label>
                <select x-model="forma_pago" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    <option value="EFECTIVO">💵 Efectivo</option>
                    <option value="TRANSFERENCIA">🏦 Transferencia Bancaria</option>
                    <option value="TARJETA_CREDITO">💳 TARJETA CRÉDITO</option>
                    <option value="TARJETA_DEBITO">💳 TARJETA DÉBITO</option>
                    <option value="QR">📱 PAGO QR</option>
                </select>
            </div>
            
            <!-- Animaciones para campos adicionales de pago -->
            <div x-show="forma_pago === 'TRANSFERENCIA' || forma_pago === 'TARJETA_CREDITO' || forma_pago === 'TARJETA_DEBITO' || forma_pago === 'QR'" x-transition class="bg-blue-50 dark:bg-blue-900/10 p-3 rounded-xl border border-blue-100 dark:border-blue-800">
                <label class="block text-xs uppercase font-bold text-blue-600 dark:text-blue-400 mb-1">Nro. de Comprobante *</label>
                <input type="text" x-model="nro_transferencia" placeholder="Ej. 123456789" class="w-full rounded-lg border-0 bg-white dark:bg-gray-900 py-2 px-3 text-sm outline-none ring-1 ring-blue-200 dark:ring-blue-700 focus:ring-2 focus:ring-blue-500 shadow-inner">
            </div>
        </div>

        <!-- Total y Botón -->
        <div class="mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
            <div class="flex justify-between items-end mb-6">
                <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">Total a Pagar</span>
                <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight" x-text="totalFormateado"></span>
            </div>
            <button @click="procesarVenta()" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-black text-lg py-4 rounded-xl shadow-lg shadow-blue-500/30 transition-all active:scale-[0.98] flex items-center justify-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Procesar Cobro
            </button>
        </div>
    </div>

  <!-- MODAL REGISTRO DE CLIENTE (Dentro del PDV) -->
    <div x-show="showModalCliente" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="showModalCliente = false" class="bg-white dark:bg-[#1c2434] w-full max-w-3xl rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">👤 Registrar Nuevo Cliente</h3>
                <button @click="showModalCliente = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <!-- Usamos form con prevent para validar los campos requeridos (required) -->
            <form @submit.prevent="guardarClienteAjax()" class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">RUC / CI *</label>
                        <input type="text" x-model="nuevoCli.cli_ruc_ci" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Teléfono *</label>
                        <input type="text" x-model="nuevoCli.cli_telefono" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Nombre *</label>
                        <input type="text" x-model="nuevoCli.cli_nombre" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Apellido *</label>
                        <input type="text" x-model="nuevoCli.cli_apellido" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Dirección Física *</label>
                    <input type="text" x-model="nuevoCli.cli_direccion" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Correo Electrónico *</label>
                        <input type="email" x-model="nuevoCli.cli_email" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Límite Crédito (Gs.)</label>
                        <input type="number" x-model="nuevoCli.cli_limite_credito" value="0" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-2 bg-blue-50 dark:bg-blue-900/10 p-3 rounded-lg border border-blue-100 dark:border-blue-800/50">
                    <input type="checkbox" id="cli_es_mayorista" x-model="nuevoCli.cli_es_mayorista" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600">
                    <label for="cli_es_mayorista" class="text-sm font-medium text-blue-800 dark:text-blue-300 cursor-pointer">Activar Precios Mayoristas para este cliente</label>
                </div>
                
                <div class="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-800 mt-4">
                    <button type="button" @click="showModalCliente = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Cancelar</button>
                    <!-- Al estar dentro de un form, el type="submit" gatillará el required y luego la función guardarClienteAjax() -->
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700 transition">Guardar y Seleccionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Estilizar barras de scroll para que no ensucien el diseño */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function pdvApp() {
        return {
            // --- VARIABLES EXISTENTES ---
            searchProduct: '', 
            productos: @json($productos), 
            clientes: @json($clientes),
            carrito: [], 
            cli_id: '', 
            moneda: 'GS', 
            vta_tipo: 'CONTADO', 
            forma_pago: 'EFECTIVO', 
            nro_transferencia: '',
            cotizaciones: @json($cotizaciones),
            showModalCliente: false,
            nuevoCli: { cli_ruc_ci: '', cli_nombre: '', cli_telefono: '', cli_es_mayorista: false },

            // --- VARIABLES PARA EL BUSCADOR DE CLIENTES ---
            searchCliente: '',
            clienteSeleccionado: null,

            // --- INIT (Vigila cuando seleccionas un cliente) ---

            init() {
                this.$watch('clienteSeleccionado', (nuevoCliente) => {
                    // Simplemente llamamos a la función segura que ya creamos abajo
                    this.actualizarPreciosMayorista();
                });
            },

            // --- FUNCIONES PARA CLIENTES ---
            get clientesFiltrados() {
                if (this.searchCliente === '' || this.clienteSeleccionado !== null) {
                    return this.clientes;
                }
                let q = this.searchCliente.toLowerCase();
                return this.clientes.filter(c => 
                    (c.cli_nombre && c.cli_nombre.toLowerCase().includes(q)) || 
                    (c.cli_apellido && c.cli_apellido.toLowerCase().includes(q)) ||
                    (c.cli_ruc_ci && String(c.cli_ruc_ci).toLowerCase().includes(q))
                );
            },

            seleccionarCliente(cli) {
                this.clienteSeleccionado = cli;
                this.cli_id = cli.cli_id;
                this.searchCliente = cli.cli_ruc_ci + ' - ' + cli.cli_nombre + ' ' + (cli.cli_apellido || '');
            },

            guardarClienteAjax() {
                fetch('{{ route("pdv.cliente.ajax") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify(this.nuevoCli)
                }).then(res => res.json()).then(data => {
                    if(data.success) {
                        this.clientes.push(data.cliente);
                        this.seleccionarCliente(data.cliente); 
                        this.showModalCliente = false;
                        this.nuevoCli = { cli_ruc_ci: '', cli_nombre: '', cli_telefono: '', cli_es_mayorista: false };
                        Swal.fire('Éxito', 'Cliente registrado', 'success');
                    }
                });
            },

            // --- FUNCIONES PARA PRODUCTOS Y CARRITO ---
            get filteredProducts() {
                let term = this.searchProduct.toLowerCase();
                if(term === '') return this.productos.slice(0, 8);
                return this.productos.filter(p => p.pro_nombre.toLowerCase().includes(term) || (p.pro_codigo && p.pro_codigo.toLowerCase().includes(term)));
            },


            actualizarPreciosMayorista() {
                // 1. Identificar si el cliente actual es mayorista de forma segura
                let clienteObj = this.clientes.find(c => c.cli_id == this.cli_id);
                let esMayorista = clienteObj ? (clienteObj.cli_es_mayorista == 1 || clienteObj.cli_es_mayorista === true) : false;

                // 2. Recorrer los productos que ya están en el carrito para actualizar su precio
                this.carrito.forEach(item => {
                    let pInfo = this.productos.find(p => p.pro_id === item.pro_id);
                    
                    if (pInfo) {
                        // Extraer los valores forzándolos a formato numérico
                        let precioNormal = parseFloat(pInfo.pro_precioventa) || 0;
                        let precioPromo = parseFloat(pInfo.precio_promocional) || precioNormal;
                        let precioMayorista = parseFloat(pInfo.pro_preciomayorista) || 0;

                        // Determinar precio base (Promo o Normal)
                        let precioBase = pInfo.en_promocion ? precioPromo : precioNormal;

                        // Determinar el precio final priorizando al mayorista
                        let precioFinal = (esMayorista && precioMayorista > 0) ? precioMayorista : precioBase;

                        // Actualizar el ítem en el carrito
                        item.precio = precioFinal;
                        item.subtotal = item.cantidad * item.precio;
                    }
                });
            },

            agregarAlCarrito(pro) {
                let qtyActual = this.carrito.find(i => i.pro_id === pro.pro_id)?.cantidad || 0;
                if (qtyActual + 1 > pro.pro_stockactual) {
                    Swal.fire('Sin Stock', 'No stock suficiente.', 'error');
                    return;
                }

                // 1. Extraer los valores forzándolos a formato numérico (evita el NaN)
                let precioNormal = parseFloat(pro.pro_precioventa) || 0;
                let precioPromo = parseFloat(pro.precio_promocional) || precioNormal;
                let precioMayorista = parseFloat(pro.pro_preciomayorista) || 0;

                // 2. Determinar precio base (Promo o Normal)
                let precioBase = pro.en_promocion ? precioPromo : precioNormal;

                // 3. Verificar si el cliente seleccionado es mayorista
                // Usamos == por si this.cli_id es un string y c.cli_id es un número
                let clienteObj = this.clientes.find(c => c.cli_id == this.cli_id);
                
                // Aseguramos leer el booleano correctamente (puede venir como 1 o true desde la BD)
                let esMayorista = clienteObj ? (clienteObj.cli_es_mayorista == 1 || clienteObj.cli_es_mayorista === true) : false;

                // 4. Determinar el precio final
                let precioFinal = (esMayorista && precioMayorista > 0) ? precioMayorista : precioBase;
                
                let index = this.carrito.findIndex(item => item.pro_id === pro.pro_id);
                if (index !== -1) {
                    this.carrito[index].cantidad++;
                    this.carrito[index].subtotal = this.carrito[index].cantidad * this.carrito[index].precio;
                } else {
                    this.carrito.push({ 
                        pro_id: pro.pro_id, 
                        pro_nombre: pro.pro_nombre, 
                        precio: precioFinal, 
                        cantidad: 1, 
                        subtotal: precioFinal 
                    });
                }
            },

            actualizarSubtotal(index) {
                let pro = this.productos.find(p => p.pro_id === this.carrito[index].pro_id);
                if(this.carrito[index].cantidad > pro.pro_stockactual) {
                    Swal.fire('Aviso', 'Cantidad supera el stock. Ajustado al máximo.', 'warning');
                    this.carrito[index].cantidad = pro.pro_stockactual;
                }
                this.carrito[index].subtotal = this.carrito[index].cantidad * this.carrito[index].precio;
            },

            eliminarDelCarrito(index) { 
                this.carrito.splice(index, 1); 
            },

            get totalFormateado() {
                let totalGs = this.carrito.reduce((sum, i) => sum + i.subtotal, 0);
                if (this.moneda === 'USD') return '$ ' + (totalGs / this.cotizaciones.USD).toLocaleString('en-US', {minimumFractionDigits: 2});
                if (this.moneda === 'BRL') return 'R$ ' + (totalGs / this.cotizaciones.BRL).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                return 'Gs. ' + totalGs.toLocaleString('es-PY');
            },
            
            formatMoneda(val) { 
                return 'Gs. ' + Number(val).toLocaleString('es-PY'); 
            },

            // --- PROCESAR COBRO ---
            procesarVenta() {
                if (!this.cli_id) {
                    Swal.fire('Atención', 'Debe seleccionar un cliente antes de cobrar.', 'warning');
                    return;
                }
                if (this.carrito.length === 0) {
                    Swal.fire('Atención', 'El carrito de compras está vacío.', 'warning');
                    return;
                }
                if (this.forma_pago === 'TRANSFERENCIA' && !this.nro_transferencia.trim()) {
                    Swal.fire('Atención', 'Debe ingresar el código de la transferencia.', 'warning');
                    return;
                }

                fetch('{{ route("pdv.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        cli_id: this.cli_id,
                        vta_tipo: this.vta_tipo,
                        forma_pago: this.forma_pago,
                        nro_transferencia: this.nro_transferencia,
                        moneda: this.moneda,
                        carrito: this.carrito,
                        caj_id: localStorage.getItem('dbstock_terminal_id') 
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.venta_id) {
                            let rutaTicket = (data.tipo_impresion === 'TICKET_FACTURA') 
                                ? '/pdv/ticket-factura/' + data.venta_id 
                                : '/pdv/ticket-simple/' + data.venta_id;
                            window.open(rutaTicket, 'Ticket', 'width=400,height=600');
                        }
                        Swal.fire({
                            title: '¡Venta Exitosa!',
                            text: data.message,
                            icon: 'success',
                            showConfirmButton: false, 
                            timer: 1000,              
                            timerProgressBar: true    
                        }).then(() => {
                            window.location.reload(); 
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error("Error en la petición:", err);
                    Swal.fire('Error', 'Ocurrió un error inesperado al procesar la venta.', 'error');
                });
            }
        }
    }
</script>

@endsection