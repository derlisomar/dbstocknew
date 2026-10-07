@extends('layouts.admin')
@section('contenido')
<div x-data="cobranzasApp()" class="space-y-5">
    <!-- Header y Buscador -->
    <div class="flex justify-between items-center bg-white dark:bg-[#1c2434] p-5 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
        <div>
            <h2 class="text-xl font-black text-gray-800 dark:text-white">💰 Módulo de Cobranzas</h2>
            <p class="text-xs text-gray-400">Gestión de créditos y cuotas. El dinero ingresará a tu caja activa.</p>
        </div>
        <div class="relative w-80">
            <input type="text" x-model="search" placeholder="🔍 Buscar por Cliente o Nro de Venta..." class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 py-2.5 px-4 text-sm text-gray-800 dark:text-white outline-none">
        </div>
    </div>

    <!-- Tabla de Cuentas por Cobrar -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50/80 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800 text-[11px] uppercase font-black text-gray-500">
                <tr>
                    <th class="py-4 px-5">Venta Nro.</th>
                    <th class="py-4 px-5">Cliente</th>
                    <th class="py-4 px-5 text-right text-gray-800 dark:text-gray-200">Total Deuda</th>
                    <th class="py-4 px-5 text-right text-green-600">Ya Pagado</th>
                    <th class="py-4 px-5 text-right text-red-500">Falta Pagar</th>
                    <th class="py-4 px-5 text-center">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <template x-for="cuenta in cuentasFiltradas" :key="cuenta.cred_id">
                    <tr class="hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors">
                        <td class="py-3 px-5">
                            <span class="font-bold text-gray-900 dark:text-white block" x-text="'#' + cuenta.vta_id"></span>
                            <span class="text-[11px] text-gray-400" x-text="'Vence: ' + cuenta.cred_fecha_vencimiento"></span>
                        </td>
                        <td class="py-3 px-5 font-bold text-gray-700 dark:text-gray-300" x-text="cuenta.cliente?.cli_nombre + ' ' + (cuenta.cliente?.cli_apellido || '')"></td>
                        
                        <!-- Cálculos Financieros -->
                        <td class="py-3 px-5 text-right font-bold text-gray-600 dark:text-gray-400" x-text="formatMoneda(cuenta.cred_monto_total)"></td>
                        <td class="py-3 px-5 text-right font-black text-green-600" x-text="formatMoneda(cuenta.cred_monto_total - cuenta.cred_saldo_pendiente)"></td>
                        <td class="py-3 px-5 text-right font-black text-red-500" x-text="formatMoneda(cuenta.cred_saldo_pendiente)"></td>
                        
                        <td class="py-3 px-5 text-center">
                            <button @click="abrirModal(cuenta)" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-5 rounded-lg text-xs transition shadow-sm">
                                💲 Cobrar
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="cuentasFiltradas.length === 0">
                    <td colspan="6" class="py-10 text-center text-gray-500 font-medium">No se encontraron deudas pendientes...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- MODAL DE COBRO (Pagos a Cuotas) -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;" @click.away="showModal = false">
        <div class="bg-white dark:bg-[#1c2434] w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-800" @click.stop>
            
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
                <div>
                    <h3 class="text-base font-black text-gray-800 dark:text-white">💵 Ingreso de Cobro</h3>
                    <p class="text-[11px] text-gray-400">Venta a Crédito Nro <span x-text="cuentaActiva?.vta_id"></span></p>
                </div>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 font-bold text-lg">✕</button>
            </div>

            <div class="p-6 space-y-4">
                <!-- Resumen de Deuda -->
                <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-xl border border-red-100 dark:border-red-800 flex justify-between items-center">
                    <span class="text-xs font-bold text-red-700 dark:text-red-300">Saldo Falta Pagar:</span>
                    <span class="text-sm font-black text-red-700 dark:text-red-400" x-text="cuentaActiva ? formatMoneda(cuentaActiva.cred_saldo_pendiente) : 0"></span>
                </div>

                <!-- NUEVO: Historial de Pagos Realizados -->
                <div x-show="cuentaActiva && cuentaActiva.detalles_cobranza && cuentaActiva.detalles_cobranza.length > 0" class="mb-4">
                    <h4 class="text-[11px] uppercase font-bold text-gray-500 mb-2">Historial de Pagos</h4>
                    <div class="max-h-32 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-500 sticky top-0">
                                <tr>
                                    <th class="py-2 px-3">Fecha / Destino</th>
                                    <th class="py-2 px-3 text-right">Abono</th>
                                    <th class="py-2 px-3 text-center">Ticket</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="detalle in cuentaActiva?.detalles_cobranza" :key="detalle?.det_cob_id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="py-2 px-3">
                                            <span class="block font-semibold text-gray-800 dark:text-gray-200" x-text="formatFecha(detalle.cobranza?.cob_fecha)"></span>
                                            <span class="text-[10px] text-gray-400" x-text="'Caja ' + (detalle.cobranza?.sesion?.caja?.caj_nombre || 'Principal')"></span>
                                        </td>
                                        <td class="py-2 px-3 text-right font-bold text-green-600" x-text="formatMoneda(detalle.det_monto_pagado)"></td>
                                        <td class="py-2 px-3 text-center">
                                            <button type="button" @click="imprimirTicket(detalle.cob_id)" class="text-gray-500 hover:text-blue-600 font-bold" title="Imprimir Ticket">
                                                🖨️
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Input Monto -->
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Monto a Abonar (Gs.) *</label>
                    <input type="number" x-model.number="monto_pagar" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-3 px-4 text-lg font-black text-blue-600 dark:text-blue-400 outline-none">
                    <p class="text-[10px] text-gray-400 mt-1">Puede ingresar un pago parcial para cuotas.</p>
                </div>

                <!-- Input Observación -->
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 mb-1">Observación</label>
                    <textarea x-model="observacion" rows="2" class="w-full rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" placeholder="Ej: Pago de la cuota 1/3..."></textarea>
                </div>

                <!-- Botón Confirmar -->
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" @click="procesarCobro()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg text-sm">
                        Confirmar y Enviar a Caja
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function cobranzasApp() {
        return {
            cuentas: @json($cuentas),
            search: '',
            showModal: false,
            cuentaActiva: null,
            monto_pagar: '',
            observacion: '',

            // Buscador dinámico
            get cuentasFiltradas() {
                if (this.search === '') return this.cuentas;
                let term = this.search.toLowerCase();
                return this.cuentas.filter(c => 
                    (c.vta_id && String(c.vta_id).includes(term)) ||
                    (c.cliente?.cli_nombre && c.cliente.cli_nombre.toLowerCase().includes(term)) ||
                    (c.cliente?.cli_apellido && c.cliente.cli_apellido.toLowerCase().includes(term))
                );
            },

            // Formateo de Dinero (Si esta función se borra, la tabla queda en blanco)
            formatMoneda(val) {
                return 'Gs. ' + Number(val).toLocaleString('es-PY');
            },

            // Formateo de Fecha para el Historial
            formatFecha(fechaStr) {
                if (!fechaStr) return 'Fecha no reg.';
                let date = new Date(fechaStr);
                return date.toLocaleDateString('es-PY') + ' ' + date.toLocaleTimeString('es-PY', {hour: '2-digit', minute:'2-digit'});
            },

            // Imprimir el Ticket
            imprimirTicket(cobId) {
                window.open('/cobranzas/ticket/' + cobId, 'TicketCobro', 'width=400,height=600');
            },

            abrirModal(cuenta) {
                this.cuentaActiva = cuenta;
                this.monto_pagar = cuenta.cred_saldo_pendiente; // Sugiere cobrar el total
                this.observacion = '';
                this.showModal = true;
            },

            procesarCobro() {
                if (!this.monto_pagar || this.monto_pagar <= 0) {
                    Swal.fire('Error', 'Ingrese un monto mayor a 0', 'error');
                    return;
                }
                if (this.monto_pagar > this.cuentaActiva.cred_saldo_pendiente) {
                    Swal.fire('Error', 'El cliente no puede pagar más de lo que debe (' + this.formatMoneda(this.cuentaActiva.cred_saldo_pendiente) + ')', 'warning');
                    return;
                }

                fetch('{{ route("cobranzas.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json', // <--- NUEVA LÍNEA: Obliga a recibir errores reales
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        cred_id: this.cuentaActiva.cred_id, // <--- AQUÍ ESTABA EL ERROR, debe ser this.cuentaActiva.cred_id
                        monto_pagar: this.monto_pagar,
                        observacion: this.observacion,
                        caj_id: localStorage.getItem('dbstock_terminal_id') // Mantiene la lectura de la caja correcta
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Cobrado!', data.message, 'success').then(() => {
                            window.location.reload(); 
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Ocurrió un problema al cobrar', 'error'));
            }
        }
    }
</script>

@endsection