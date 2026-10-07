@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    openCierreModal: false,
    openAperturaModal: false,
    ses_id: '',
    caj_id: '',
    caj_nombre: '',
    
    /* Variables Cierre */
    cierre_gs: 0,
    cierre_usd: 0,
    cierre_brl: 0,
    
    /* Variables Apertura */
    ses_monto_inicial_gs: 0,
    ses_monto_inicial_usd: 0,
    ses_monto_inicial_brl: 0,

    abrirModalApertura(cajId, cajNombre) {
        this.caj_id = cajId;
        this.caj_nombre = cajNombre;
        this.ses_monto_inicial_gs = 0;
        this.ses_monto_inicial_usd = 0;
        this.ses_monto_inicial_brl = 0;
        this.openAperturaModal = true;
    },

    abrirModalCierre(sesId, cajNombre) {
        this.ses_id = sesId;
        this.caj_nombre = cajNombre;
        this.cierre_gs = 0;
        this.cierre_usd = 0;
        this.cierre_brl = 0;
        this.openCierreModal = true;
    }
}" class="space-y-6">
    
    <!-- Cabecera -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">💰 Estado Actual y Arqueo de Cajas</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Monitoreo interactivo de saldos con desglose completo y gestión de cierres.</p>
        </div>
    </div>

    <!-- Alertas Flash -->
    @if(session('success'))
        <div class="p-4 bg-emerald-100 dark:bg-emerald-500/10 border border-emerald-400 text-emerald-700 dark:text-emerald-400 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Tabla Principal con Despliegue de Detalles -->
    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg shadow-default overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-4 px-6">Sucursal / Caja</th>
                        <th class="py-4 px-6 text-right">Saldo Gs. (Consolidado)</th>
                        <th class="py-4 px-6 text-right">Saldo USD / BRL</th>
                        <th class="py-4 px-6 text-center">Estado Turno</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>
                
                @forelse($cajas as $caja)
                    @php
                        $sesionAbierta = \App\Models\CajaSesion::where('caj_id', $caja->caj_id)->where('ses_estado', 'ABIERTA')->first();
                        
                        $sumaVentas = 0;
                        $sumaIngresosExtras = 0;
                        $sumaCobranzas = 0;

                        if ($sesionAbierta) {
                            $sumaVentas = \App\Models\Venta::where('ses_id', $sesionAbierta->ses_id)->where('vta_tipo', 'CONTADO')->where('vta_estado', 'CONFIRMADA')->sum('vta_total');
                            $sumaIngresosExtras = \App\Models\IngresoEgreso::where('ses_id', $sesionAbierta->ses_id)->where('ie_tipo', 'INGRESO')->sum('ie_monto');
                            $sumaCobranzas = \App\Models\Cobranza::where('ses_id', $sesionAbierta->ses_id)->where('cob_estado', 'ACTIVA')->sum('cob_monto_total');
                        }
                    @endphp
                    
                    <!-- ENVOLVEMOS LAS FILAS EN UN TBODY PARA COMPARTIR EL x-data DEL DESGLOSE -->
                    <tbody x-data="{ verDetalle: false }" class="border-b border-gray-100 dark:border-gray-800 text-sm">
                        
                        <!-- FILA PRINCIPAL -->
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            <td class="py-4 px-6">
                                <span class="font-bold text-blue-600 dark:text-blue-400 block">{{ $caja->sucursal->suc_nombre ?? 'Sin Sucursal' }}</span>
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">🧮 {{ $caja->caj_nombre }}</span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <!-- Botón que abre el desglose -->
                                <button @click="verDetalle = !verDetalle" class="flex flex-col items-end w-full group">
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 text-lg group-hover:underline">
                                        Gs. {{ number_format($caja->caj_saldo_gs ?? 0, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase flex items-center gap-1">
                                        <span x-text="verDetalle ? 'Ocultar Desglose ▲' : 'Ver Desglose ▼'"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="py-4 px-6 text-right text-xs">
                                <div>$ {{ number_format($caja->caj_saldo_usd ?? 0, 2, '.', ',') }}</div>
                                <div class="text-gray-400">R$ {{ number_format($caja->caj_saldo_brl ?? 0, 2, '.', ',') }}</div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($sesionAbierta)
                                    <span class="px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700 rounded-full">Turno Abierto</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Cerrada</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($sesionAbierta)
                                    <!-- LLAMADA CORRECTA AL MODAL DE CIERRE -->
                                    <button @click="abrirModalCierre({{ $sesionAbierta->ses_id }}, '{{ $caja->caj_nombre }}')" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition shadow flex items-center justify-center gap-1 mx-auto">
                                        🔒 Realizar Cierre
                                    </button>
                                @else
                                    <!-- NUEVO BOTÓN PARA ABRIR EL MODAL DE APERTURA -->
                                    <button @click="abrirModalApertura({{ $caja->caj_id }}, '{{ $caja->caj_nombre }}')" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition shadow inline-block">
                                        🚀 Aperturar turno
                                    </button>
                                @endif
                            </td>
                        </tr>

                        <!-- FILA DE DESGLOSE -->
                        <tr x-show="verDetalle" x-collapse class="bg-gray-50/50 dark:bg-[#1a2130] border-t-0" style="display: none;">
                            <td colspan="5" class="px-6 py-4">
                                @if($sesionAbierta)
                                    <div class="grid grid-cols-3 gap-6 text-center divide-x divide-gray-200 dark:divide-gray-800">
                                        <div class="px-4">
                                            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">🛒 Ventas Contado</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-white">Gs. {{ number_format($sumaVentas, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-4">
                                            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">💰 Cobros de Créditos</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-white">Gs. {{ number_format($sumaCobranzas, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-4">
                                            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">📥 Otros Ingresos</p>
                                            <p class="text-lg font-bold text-gray-800 dark:text-white">Gs. {{ number_format($sumaIngresosExtras, 0, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-800 flex justify-center items-center gap-3">
                                        <span class="text-xs text-gray-500">Suma total de movimientos = </span>
                                        <span class="text-sm font-black text-emerald-600 bg-emerald-50 px-3 py-1 rounded border border-emerald-100">
                                            Gs. {{ number_format($sumaVentas + $sumaCobranzas + $sumaIngresosExtras, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @else
                                    <p class="text-center text-xs text-gray-500 italic py-2">No hay un turno abierto para desglosar movimientos.</p>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No hay cajas registradas.</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </div>

    <!-- MODAL DE CIERRE DE CAJA -->
    <div x-show="openCierreModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="openCierreModal = false" class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">🔐 Cierre y Arqueo de <span class="text-blue-600" x-text="caj_nombre"></span></h3>
                <button @click="openCierreModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form :action="'/finanzas/cerrar/' + ses_id" method="POST" class="p-6 space-y-4">
                @csrf
                <p class="text-xs text-gray-500 dark:text-gray-400">Ingrese el conteo físico real del efectivo en caja por moneda para cerrar la sesión y guardarla en el historial.</p>
                
                <div>
                    <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Efectivo Real en Guaraníes (Gs.) *</label>
                    <input type="number" step="0.01" name="cierre_gs" x-model="cierre_gs" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Efectivo Real USD ($)</label>
                        <input type="number" step="0.01" name="cierre_usd" x-model="cierre_usd" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-semibold text-gray-500 dark:text-gray-400 mb-1">Efectivo Real BRL (R$)</label>
                        <input type="number" step="0.01" name="cierre_brl" x-model="cierre_brl" class="w-full rounded bg-transparent border border-gray-300 dark:border-gray-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-6">
                    <button type="button" @click="openCierreModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-semibold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg shadow-md transition text-sm">Confirmar y Cerrar Caja</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE APERTURA DE CAJA -->
    <div x-show="openAperturaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="openAperturaModal = false" class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4 bg-gray-50 dark:bg-gray-900/50">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">🔓 Apertura de Turno: <span class="text-blue-600" x-text="caj_nombre"></span></h3>
                <button @click="openAperturaModal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            
            <form action="{{ route('finanzas.apertura.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <!-- Se envía el ID de la caja seleccionada en un input oculto -->
                <input type="hidden" name="caj_id" x-model="caj_id">
                
                <p class="text-xs text-gray-500 dark:text-gray-400">Ingrese el saldo inicial con el que comienza el turno. Verifique los montos en cada moneda física disponible.</p>
                
                <!-- Guaraníes (Bloque Esmeralda) -->
                <div class="bg-emerald-50 dark:bg-emerald-900/20 p-3 rounded-lg border border-emerald-100 dark:border-emerald-800/50">
                    <label class="block text-xs uppercase font-bold text-emerald-700 dark:text-emerald-400 mb-1">Monto Inicial en Guaraníes (Gs.) *</label>
                    <input type="number" step="0.01" name="ses_monto_inicial_gs" x-model="ses_monto_inicial_gs" class="w-full rounded bg-white dark:bg-gray-800 border border-emerald-300 dark:border-emerald-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-emerald-500 font-bold" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Dólares (Bloque Azul) -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-100 dark:border-blue-800/50">
                        <label class="block text-xs uppercase font-bold text-blue-700 dark:text-blue-400 mb-1">Monto Inicial USD ($)</label>
                        <input type="number" step="0.01" name="ses_monto_inicial_usd" x-model="ses_monto_inicial_usd" class="w-full rounded bg-white dark:bg-gray-800 border border-blue-300 dark:border-blue-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500">
                    </div>
                    <!-- Reales (Bloque Ámbar) -->
                    <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-100 dark:border-amber-800/50">
                        <label class="block text-xs uppercase font-bold text-amber-700 dark:text-amber-400 mb-1">Monto Inicial BRL (R$)</label>
                        <input type="number" step="0.01" name="ses_monto_inicial_brl" x-model="ses_monto_inicial_brl" class="w-full rounded bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 py-2.5 px-3 text-sm text-gray-800 dark:text-white outline-none focus:border-amber-500">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-800 mt-6">
                    <button type="button" @click="openAperturaModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-semibold">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-md transition text-sm">Abrir Caja y Comenzar Turno</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection