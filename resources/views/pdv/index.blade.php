@extends('layouts.admin')

@section('contenido')

<div x-data="pdvApp()" class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-6rem)] relative">
    
    <!-- SECCIÓN IZQUIERDA: Búsqueda de Productos y Carrito (Ocupa 8 columnas) -->
    <!-- ================= SECCIÓN IZQUIERDA: Buscador Rápido y Carrito ================= -->
    <div class="xl:col-span-8 flex flex-col gap-4 h-full">
        
        <!-- Aviso cuando la venta viene de un presupuesto -->
        <div x-show="precarga" x-cloak class="flex items-center justify-between gap-3 rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/40 px-4 py-2 text-sm text-blue-800 dark:text-blue-200">
            <span>Vendiendo el presupuesto <b x-text="precarga ? precarga.numero : ''"></b>. Los precios son los vigentes de hoy y el stock se controla al cobrar.</span>
            <a href="{{ route('pdv.index') }}" class="font-semibold underline whitespace-nowrap">Quitar presupuesto</a>
        </div>

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
                    @input="openBusquedaProd = true; avisoCodigo = ''; programarLectura()"
                    @keydown.enter.prevent="leerCodigo()"
                    x-ref="buscador"
                    autocomplete="off"
                    placeholder="Escanee el código de barras o busque por nombre..." 
                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 py-3.5 pl-12 pr-4 text-sm font-medium text-gray-800 dark:text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all shadow-sm"
                    autofocus
                >
                
                <p x-show="avisoCodigo" x-text="avisoCodigo" class="mt-2 text-sm font-bold text-red-500" style="display:none"></p>

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
                                <div class="text-sm font-black text-blue-600 dark:text-blue-400 mb-1" x-text="formatMoneda(pro.pro_precioventa) + (pro.pro_unidad !== 'UN' ? ' / ' + unidades[pro.pro_unidad].corta : '')"></div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md inline-block uppercase tracking-wider" 
                                      :class="pro.pro_stockactual <= 0 ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-700'"
                                      x-text="'Stock: ' + pro.pro_stockactual + (pro.pro_unidad !== 'UN' ? ' ' + unidades[pro.pro_unidad].corta : '')"></span>
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
                                    <div class="flex items-center justify-center gap-1">
                                        <template x-if="!item.fraccion">
                                            <input type="number" min="1" step="1" x-model.number="item.cantidad" @change="actualizarSubtotal(index)" class="w-16 text-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold">
                                        </template>
                                        <template x-if="item.fraccion">
                                            <div class="flex items-center gap-1">
                                                <input type="number" min="0" step="any" :id="'cant-' + index" x-model.number="item.entrada" @input="fijarCantidad(index)" class="w-24 text-center rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-bold">
                                                <select x-model="item.medida" @change="cambiarMedida(index)" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1.5 px-1 text-sm font-bold">
                                                    <option value="base" x-text="item.corta"></option>
                                                    <option value="menor" x-text="item.menor"></option>
                                                </select>
                                            </div>
                                        </template>
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
                        @input="clienteSeleccionado = null; cli_id = null; openBusqueda = true; buscarPadronCliente()"
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

                        <!-- Coincidencias del padrón de RUC de la DNIT (aún no son clientes) -->
                        <template x-for="p in padronSug" :key="p.ruc">
                            <div @click="usarPadron(p); openBusqueda = false"
                                 class="px-4 py-2.5 cursor-pointer hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-sm text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <span class="text-[10px] uppercase font-black text-emerald-600 mr-1">Padrón DNIT</span>
                                <span class="font-black text-gray-900 dark:text-white" x-text="p.ruc"></span> - <span x-text="p.completo"></span>
                                <span class="block text-[11px]" :class="p.estado && p.estado !== 'ACTIVO' ? 'text-red-500 font-bold' : 'text-gray-400'" x-text="(p.estado && p.estado !== 'ACTIVO' ? 'RUC ' + p.estado + ' · ' : '') + 'Tocá para registrarlo como cliente'"></span>
                            </div>
                        </template>

                        <!-- Mensaje por si no hay coincidencias -->
                        <div x-show="clientesFiltrados.length === 0 && padronSug.length === 0" class="px-4 py-3 text-sm text-gray-500 text-center font-medium">
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
                @modulo('multimoneda')
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-2">Moneda</label>
                    <select x-model="moneda" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <option value="GS">Guaraníes (Gs.)</option>
                        <option value="USD">Dólares (USD)</option>
                        <option value="BRL">Reales (R$)</option>
                    </select>
                </div>
                @endmodulo
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 dark:text-gray-400 mb-2">Condición</label>
                    <select x-model="vta_tipo" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 px-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <option value="CONTADO">Contado</option>
                        @modulo('cobranzas')<option value="CREDITO">Crédito</option>@endmodulo
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
                        <input type="text" x-model="nuevoCli.cli_ruc_ci" @input.debounce.400ms="buscarRucModal()" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500" required>
                        <p x-show="rucAviso" x-text="rucAviso" class="text-[11px] text-emerald-600 mt-1" style="display:none"></p>
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
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Apellido</label>
                        <input type="text" x-model="nuevoCli.cli_apellido" class="w-full rounded border border-gray-300 dark:border-gray-700 bg-transparent py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
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
            unidades: @json($unidades),
            urlCodigo: @json(route('pdv.codigo')),
            avisoCodigo: '',
            urlPadron: @json(route('padron.ruc')),
            padronSug: [],
            timerPadron: null,
            rucAviso: '',
            timerLectura: null,
            clientes: @json($clientes),
            carrito: [], 
            cli_id: '', 
            moneda: 'GS', 
            vta_tipo: 'CONTADO', 
            forma_pago: 'EFECTIVO', 
            nro_transferencia: '',
            cotizaciones: @json($cotizaciones),
            precarga: @json($precarga ?? null),
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

                if (this.precarga) { this.cargarPresupuesto(); }
            },

            // --- VENTA DESDE UN PRESUPUESTO: carga cliente y productos ---
            cargarPresupuesto() {
                const avisos = [];
                const cli = this.precarga.cli_id ? this.clientes.find(c => c.cli_id == this.precarga.cli_id) : null;
                if (cli) {
                    this.seleccionarCliente(cli);
                } else {
                    avisos.push('El presupuesto es para «' + this.precarga.cliente_nombre + '», que no es un cliente registrado. Elegí o creá el cliente antes de cobrar.');
                }

                this.precarga.items.forEach(it => {
                    const pro = this.productos.find(p => p.pro_id == it.pro_id);
                    if (!pro) { avisos.push('Un producto del presupuesto ya no está disponible.'); return; }
                    const stock = parseFloat(pro.pro_stockactual) || 0;
                    if (stock <= 0) { avisos.push('«' + pro.pro_nombre + '» no tiene stock y no se agregó.'); return; }

                    this.agregarAlCarrito(pro);
                    const i = this.carrito.findIndex(x => x.pro_id === pro.pro_id);
                    if (i === -1) { return; }
                    const pedido = parseFloat(it.cantidad) || 1;
                    const cantidad = Math.min(pedido, stock);
                    if (cantidad < pedido) { avisos.push('«' + pro.pro_nombre + '»: se presupuestaron ' + pedido + ' y solo hay ' + stock + ' en stock.'); }
                    this.ponerCantidad(i, cantidad);
                });

                if (avisos.length) {
                    setTimeout(() => Swal.fire({ title: 'Revisá antes de cobrar', html: avisos.map(a => '<p style="margin:4px 0">' + a + '</p>').join(''), icon: 'warning' }), 300);
                }
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

            async consultarRuc(q) {
                try {
                    const r = await fetch(this.urlPadron + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    if (r.ok) { return await r.json(); }
                } catch (e) {}
                return { existente: null, padron: [] };
            },

            // Mientras se escribe un RUC en el buscador de clientes: sugiere los del padrón de la DNIT que todavía no son clientes.
            buscarPadronCliente() {
                clearTimeout(this.timerPadron);
                this.padronSug = [];
                const t = this.searchCliente.trim();
                if (!/^\d{4,12}(-\d?)?$/.test(t)) { return; }
                this.timerPadron = setTimeout(async () => {
                    const d = await this.consultarRuc(t);
                    this.padronSug = (d.padron || []).filter(p => !this.clientes.some(c => String(c.cli_ruc_ci) === p.ruc));
                }, 300);
            },

            usarPadron(p) {
                this.nuevoCli = { cli_ruc_ci: p.ruc, cli_nombre: p.nombre, cli_apellido: p.apellido || '', cli_telefono: '', cli_es_mayorista: false };
                this.rucAviso = 'Datos completados desde el padrón de la DNIT' + (p.estado && p.estado !== 'ACTIVO' ? ' — ATENCIÓN: RUC ' + p.estado : '');
                this.padronSug = [];
                this.showModalCliente = true;
            },

            // En el formulario de cliente nuevo: al escribir el RUC completa el nombre, o avisa si ya es cliente.
            async buscarRucModal() {
                const t = (this.nuevoCli.cli_ruc_ci || '').trim();
                this.rucAviso = '';
                if (!/^\d{4,12}(-\d)?$/.test(t)) { return; }
                const d = await this.consultarRuc(t);
                if (d.existente) {
                    const c = this.clientes.find(x => x.cli_id === d.existente.cli_id) || d.existente;
                    if (!this.clientes.some(x => x.cli_id === c.cli_id)) { this.clientes.push(c); }
                    this.seleccionarCliente(c);
                    this.showModalCliente = false;
                    Swal.fire('Ya es cliente', 'Se seleccionó a ' + c.cli_nombre + ' ' + (c.cli_apellido || ''), 'info');
                    return;
                }
                const exacto = (d.padron || []).filter(p => p.ruc === t || p.ruc.startsWith(t + '-'));
                if (exacto.length === 1) {
                    if (!this.nuevoCli.cli_nombre) { this.nuevoCli.cli_nombre = exacto[0].nombre; this.nuevoCli.cli_apellido = exacto[0].apellido || ''; }
                    if (!t.includes('-')) { this.nuevoCli.cli_ruc_ci = exacto[0].ruc; }
                    const est = exacto[0].estado;
                    this.rucAviso = 'Datos completados desde el padrón de la DNIT' + (est && est !== 'ACTIVO' ? ' — ATENCIÓN: RUC ' + est : '');
                } else if ((d.padron || []).length === 0 && t.length >= 6) {
                    this.rucAviso = 'No figura en el padrón cargado: completá los datos a mano.';
                }
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
                return this.productos.filter(p => p.pro_nombre.toLowerCase().includes(term) || (p.pro_codigo && p.pro_codigo.toLowerCase().includes(term)) || (p.barras || []).some(b => b.toLowerCase().includes(term)));
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
                        item.subtotal = this.importe(item.cantidad, item.precio);
                    }
                });
            },

            // Redondeos: las cantidades llevan hasta 3 decimales y los importes se redondean a guaraníes enteros en kilos y litros.
            redondear3(n) { return Math.round((parseFloat(n) || 0) * 1000) / 1000; },
            importe(cantidad, precio) { return Math.round(cantidad * precio * 100) / 100; },
            unidadDe(pro) { return pro.pro_unidad || 'UN'; },
            esFraccion(pro) { return this.unidadDe(pro) !== 'UN'; },

            // Pone una cantidad (en kilos/litros o unidades) en un renglón del carrito.
            ponerCantidad(index, cantidad) {
                const it = this.carrito[index];
                it.cantidad = this.redondear3(cantidad);
                if (it.fraccion) {
                    const f = this.unidades[it.unidad].factor;
                    it.entrada = it.medida === 'menor' ? this.redondear3(it.cantidad * f) : it.cantidad;
                }
                it.subtotal = it.fraccion ? Math.round(it.cantidad * it.precio) : this.importe(it.cantidad, it.precio);
            },

            // El cajero escribió una cantidad en kilos/litros o en gramos/mililitros.
            fijarCantidad(index) {
                const it = this.carrito[index];
                const f = this.unidades[it.unidad].factor;
                const entrada = parseFloat(it.entrada) || 0;
                let cant = this.redondear3(it.medida === 'menor' ? entrada / f : entrada);
                const pro = this.productos.find(p => p.pro_id === it.pro_id);
                if (pro && cant > pro.pro_stockactual) {
                    Swal.fire('Aviso', 'Cantidad supera el stock. Ajustado al máximo.', 'warning');
                    cant = parseFloat(pro.pro_stockactual);
                    it.entrada = it.medida === 'menor' ? this.redondear3(cant * f) : cant;
                }
                it.cantidad = cant;
                it.subtotal = Math.round(cant * it.precio);
            },

            // Cambió de kilos a gramos (o litros a mililitros): se muestra la misma cantidad en la otra medida.
            cambiarMedida(index) {
                const it = this.carrito[index];
                const f = this.unidades[it.unidad].factor;
                it.entrada = it.medida === 'menor' ? this.redondear3(it.cantidad * f) : it.cantidad;
            },

            // `cantidad` viene de una etiqueta de balanza (kilos/litros); si es null suma 1 (o pide la cantidad en kilos/litros).
            agregarAlCarrito(pro, cantidad = null) {
                const fraccion = this.esFraccion(pro);
                const aSumar = cantidad !== null ? this.redondear3(cantidad) : 1;
                const index0 = this.carrito.findIndex(item => item.pro_id === pro.pro_id);
                const qtyActual = index0 !== -1 ? this.carrito[index0].cantidad : 0;
                if (qtyActual + aSumar > parseFloat(pro.pro_stockactual) + 0.0000001) {
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
                let clienteObj = this.clientes.find(c => c.cli_id == this.cli_id);
                let esMayorista = clienteObj ? (clienteObj.cli_es_mayorista == 1 || clienteObj.cli_es_mayorista === true) : false;

                // 4. Determinar el precio final
                let precioFinal = (esMayorista && precioMayorista > 0) ? precioMayorista : precioBase;

                let index = index0;
                if (index !== -1) {
                    this.ponerCantidad(index, qtyActual + aSumar);
                } else {
                    const u = this.unidades[this.unidadDe(pro)];
                    this.carrito.push({
                        pro_id: pro.pro_id,
                        pro_nombre: pro.pro_nombre,
                        precio: precioFinal,
                        cantidad: 0,
                        subtotal: 0,
                        fraccion: fraccion,
                        unidad: this.unidadDe(pro),
                        corta: u.corta,
                        menor: u.menor,
                        medida: 'base',
                        entrada: 0
                    });
                    index = this.carrito.length - 1;
                    this.ponerCantidad(index, aSumar);
                }

                // Producto de peso agregado a mano: deja el cursor en la cantidad para escribir los gramos.
                if (fraccion && cantidad === null) {
                    this.$nextTick(() => { const el = document.getElementById('cant-' + index); if (el) { el.focus(); el.select(); } });
                }
            },

            // Lector de códigos sin Enter: si lo escrito parece un código (8 a 14 dígitos, o un código de barra ya
            // registrado) y se deja de escribir un instante, se agrega solo. Los nombres siguen necesitando Enter.
            programarLectura() {
                clearTimeout(this.timerLectura);
                const t = this.searchProduct.trim();
                const parece = /^\d{8,14}$/.test(t) || this.productos.some(p => (p.barras || []).includes(t));
                if (!parece) { return; }
                this.timerLectura = setTimeout(() => this.leerCodigo(), 120);
            },

            // Enter en el buscador (o lectura automática): código de barras, etiqueta de balanza o búsqueda por nombre.
            async leerCodigo() {
                clearTimeout(this.timerLectura);
                const texto = this.searchProduct.trim();
                if (!texto) { return; }
                this.avisoCodigo = '';

                let hallado = null;
                try {
                    const r = await fetch(this.urlCodigo + '?codigo=' + encodeURIComponent(texto), { headers: { 'Accept': 'application/json' } });
                    if (r.ok) { hallado = await r.json(); }
                } catch (e) {
                    // Sin conexión con el servidor: se busca el código exacto entre los productos ya cargados.
                    const p = this.productos.find(p => p.pro_codigo === texto || (p.barras || []).includes(texto));
                    if (p) { hallado = { ok: true, pro_id: p.pro_id, cantidad: null }; }
                }

                if (hallado && hallado.ok) {
                    const pro = this.productos.find(p => p.pro_id === hallado.pro_id);
                    if (pro) {
                        this.agregarAlCarrito(pro, hallado.cantidad);
                        this.limpiarBuscador();
                        return;
                    }
                }

                // No era un código: si por nombre queda un solo producto, se agrega; si hay varios se elige de la lista.
                const lista = this.filteredProducts;
                if (lista.length === 1 && !/^\d{6,}$/.test(texto)) {
                    this.agregarAlCarrito(lista[0]);
                    this.limpiarBuscador();
                    return;
                }
                if (/^\d{6,}$/.test(texto) || lista.length === 0) {
                    this.avisoCodigo = 'Código no registrado: ' + texto;
                    this.searchProduct = '';
                    this.openBusquedaProd = false;
                    this.$refs.buscador.focus();
                }
            },

            limpiarBuscador() {
                this.searchProduct = '';
                this.openBusquedaProd = false;
                this.$nextTick(() => this.$refs.buscador.focus());
            },

            actualizarSubtotal(index) {
                let pro = this.productos.find(p => p.pro_id === this.carrito[index].pro_id);
                let cant = parseFloat(this.carrito[index].cantidad) || 1;
                if (cant > pro.pro_stockactual) {
                    Swal.fire('Aviso', 'Cantidad supera el stock. Ajustado al máximo.', 'warning');
                    cant = pro.pro_stockactual;
                }
                this.ponerCantidad(index, cant);
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
                        presupuesto_id: this.precarga ? this.precarga.id : null,
                        caj_id: localStorage.getItem('dbstock_terminal_id') 
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.venta_id) {
                            const rutas = {
                                TICKET_FACTURA: '/pdv/ticket-factura/',
                                MATRICIAL_SIMPLE: '/pdv/matricial-simple/',
                                MATRICIAL_FACTURA: '/pdv/matricial-factura/'
                            };
                            let rutaTicket = (rutas[data.tipo_impresion] || '/pdv/ticket-simple/') + data.venta_id;
                            // La hoja matricial es más ancha (80 columnas) que el ticket de 72 mm.
                            let medidas = (data.tipo_impresion || '').indexOf('MATRICIAL') === 0 ? 'width=900,height=700' : 'width=400,height=600';
                            window.open(rutaTicket, 'Ticket', medidas);
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