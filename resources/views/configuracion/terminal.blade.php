@extends('layouts.admin')

@section('contenido')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🖥️ Configuración del Equipo Local</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Asigna qué caja física operará en esta computadora específica.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8" x-data="terminalSetup()">
    
    <!-- Tarjeta de Configuración -->
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 p-6">
        <div class="flex items-center gap-4 mb-6 border-b border-gray-100 dark:border-gray-800 pb-4">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-xl">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Identidad de la Computadora</h3>
                <p class="text-xs text-gray-500">Los cambios solo afectarán a este navegador web.</p>
            </div>
        </div>

        <div class="space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Asignar a Sucursal y Caja:</label>
                <select id="cajaSelect" x-model="selectedCaja" class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 py-3 px-4 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all cursor-pointer">
                    <option value="">-- No asignado (Equipo Administrativo) --</option>
                    @foreach(\App\Models\Caja::with('sucursal')->where('caj_activa', true)->get() as $caja)
                        <option value="{{ $caja->caj_id }}">
                            {{ $caja->sucursal->suc_nombre ?? 'Sin Sucursal' }} - {{ $caja->caj_nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-4 rounded-xl flex gap-3">
                <span class="text-yellow-600 dark:text-yellow-500">💡</span>
                <p class="text-xs text-yellow-800 dark:text-yellow-200 leading-relaxed">
                    Al guardar, el <strong>Punto de Venta (PDV)</strong> abrirá automáticamente la sesión correspondiente a esta caja y enviará los tickets a la impresora configurada para la misma.
                </p>
            </div>

            <button @click="guardarTerminal()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition duration-300 shadow-md flex justify-center items-center gap-2">
                <span>💾</span> Guardar Configuración en este Equipo
            </button>
        </div>
    </div>

    <!-- Tarjeta de Estado Actual -->
    <div class="bg-gradient-to-br from-slate-800 to-gray-900 rounded-2xl shadow-lg border border-gray-700 p-6 text-white relative overflow-hidden">
        <!-- Decoración de fondo -->
        <div class="absolute -right-10 -top-10 opacity-10">
            <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path></svg>
        </div>
        
        <h3 class="text-lg font-bold mb-6 flex items-center gap-2"><span>📡</span> Estado Actual del Sistema</h3>
        
        <div class="space-y-4 relative z-10">
            <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                <span class="text-gray-400 text-sm">Equipo configurado como:</span>
                <span class="font-bold text-emerald-400" x-text="nombreCajaActual || 'Ninguna'"></span>
            </div>
            <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                <span class="text-gray-400 text-sm">Navegador:</span>
                <span class="font-medium text-sm text-gray-300" x-text="obtenerNavegador()"></span>
            </div>
            <div class="flex justify-between items-center border-b border-gray-700 pb-2">
                <span class="text-gray-400 text-sm">Almacenamiento Local:</span>
                <span class="text-xs bg-emerald-500/20 text-emerald-400 px-2 py-1 rounded-md font-bold">ACTIVO</span>
            </div>
        </div>
    </div>
</div>

<!-- Importación obligatoria de SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function terminalSetup() {
        return {
            selectedCaja: localStorage.getItem('dbstock_terminal_id') || '',
            nombreCajaActual: localStorage.getItem('dbstock_terminal_nombre') || '',
            tipoImpresionActual: localStorage.getItem('dbstock_terminal_tipo') || '',

            guardarTerminal() {
                // 1. Si elige "No asignado"
                if (!this.selectedCaja) {
                    localStorage.removeItem('dbstock_terminal_id');
                    localStorage.removeItem('dbstock_terminal_nombre');
                    localStorage.removeItem('dbstock_terminal_tipo');
                    this.nombreCajaActual = '';
                    this.tipoImpresionActual = '';
                    Swal.fire({ 
                        icon: 'info', 
                        title: 'Restablecido', 
                        text: 'Este equipo operará en modo administrativo (Sin caja asignada).', 
                        confirmButtonColor: '#3085d6' 
                    });
                    return;
                }

                // 2. Leer la caja seleccionada usando el ID infalible
                let selectElem = document.getElementById('cajaSelect');
                let optionElem = selectElem.options[selectElem.selectedIndex];
                
                let nombre = optionElem.text.trim();
                let tipo = optionElem.getAttribute('data-tipo') || 'TICKET_SIMPLE';

                // 3. Guardar en la memoria del navegador
                localStorage.setItem('dbstock_terminal_id', this.selectedCaja);
                localStorage.setItem('dbstock_terminal_nombre', nombre);
                localStorage.setItem('dbstock_terminal_tipo', tipo);
                
                this.nombreCajaActual = nombre;
                this.tipoImpresionActual = tipo;

                // 4. Mostrar alerta de éxito
                Swal.fire({
                    title: '¡Equipo Vinculado!',
                    html: `Esta computadora operará como:<br><br><b class="text-blue-600">${nombre}</b>`,
                    icon: 'success',
                    confirmButtonColor: '#2563eb'
                });
            },

            obtenerNavegador() {
                let ua = navigator.userAgent;
                if(ua.includes("Chrome") && !ua.includes("Edg")) return "Google Chrome";
                if(ua.includes("Edg")) return "Microsoft Edge";
                if(ua.includes("Firefox")) return "Mozilla Firefox";
                if(ua.includes("Safari") && !ua.includes("Chrome")) return "Apple Safari";
                return "Navegador Web";
            }
        }
    }
</script>
@endsection