@extends('layouts.admin')

@section('contenido')
<div x-data="{ 
    search: '',
    openModal: false, 
    editMode: false,
    cli_id: '', cli_nombre: '', cli_apellido: '', cli_ruc_ci: '',
    cli_telefono: '', cli_direccion: '', cli_email: '', cli_limite_credito: 0, cli_es_mayorista: false,
    openCreate() {
        this.editMode = false; this.cli_id = ''; this.cli_nombre = ''; this.cli_apellido = '';
        this.cli_ruc_ci = ''; this.cli_telefono = ''; this.cli_direccion = ''; this.cli_email = '';
        this.cli_limite_credito = 0; this.cli_es_mayorista = false; this.openModal = true;
    },
    openEdit(cli) {
        this.editMode = true; this.cli_id = cli.cli_id; this.cli_nombre = cli.cli_nombre;
        this.cli_apellido = cli.cli_apellido || ''; this.cli_ruc_ci = cli.cli_ruc_ci;
        this.cli_telefono = cli.cli_telefono || ''; this.cli_direccion = cli.cli_direccion || '';
        this.cli_email = cli.cli_email || ''; this.cli_limite_credito = cli.cli_limite_credito || 0;
        this.cli_es_mayorista = cli.cli_es_mayorista || false; this.openModal = true;
    }
}" class="space-y-6">
    
    <!-- Cabecera -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-gray-800 dark:text-white flex items-center gap-2">
                <span class="text-blue-600">👥</span> Gestión de Clientes
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra tu cartera, límites de crédito y categorías.</p>
        </div>
        <button @click="openCreate()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-5 py-2.5 rounded-xl font-bold transition-all shadow-lg shadow-blue-500/30 active:scale-95 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nuevo Cliente
        </button>
    </div>

    <!-- Tarjetas de Estadísticas (KPIs) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-4 relative overflow-hidden group hover:border-blue-200 transition-colors">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl text-xl">👥</div>
            <div>
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-400">Total Registrados</p>
                <h3 class="text-2xl font-black text-gray-800 dark:text-white mt-0.5">{{ $totalClientes }}</h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-4 relative overflow-hidden group hover:border-emerald-200 transition-colors">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 rounded-xl text-xl">💳</div>
            <div>
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-400">Con Línea de Crédito</p>
                <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $clientesCredito }}</h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#1c2434] p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm flex items-center gap-4 relative overflow-hidden group hover:border-amber-200 transition-colors">
            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 rounded-xl text-xl">⭐</div>
            <div>
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-400">Cuentas Mayoristas</p>
                <h3 class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-0.5">{{ $clientesMayoristas }}</h3>
            </div>
        </div>
    </div>

    <!-- Buscador de Clientes en Tiempo Real -->
    <div class="mb-4">
        <div class="relative w-full max-w-md">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">🔍</span>
            <input 
                type="text" 
                x-model="search" 
                placeholder="Buscar cliente por nombre, apellido o RUC/CI..." 
                class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 py-2.5 pl-10 pr-4 text-sm text-gray-800 dark:text-white outline-none focus:border-blue-500 transition shadow-sm"
            >
        </div>
    </div>


 <!-- Contenedor de la Tabla -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-200 text-[11px] uppercase font-bold text-gray-500 tracking-wider">
                        <th class="py-4 px-6">Documento</th>
                        <th class="py-4 px-6">Cliente</th>
                        <th class="py-4 px-6">Contacto</th>
                        <th class="py-4 px-6">Condición</th>
                        <th class="py-4 px-6 text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody class="text-sm">
                    @forelse($clientes as $cliente)
                        <!-- Fila con Búsqueda Activa y Diseño Limpio -->
                        <tr x-show="search === '' || `{{ addslashes($cliente->cli_nombre . ' ' . $cliente->cli_apellido . ' ' . $cliente->cli_ruc_ci) }}`.toLowerCase().includes(search.toLowerCase())" 
                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors last:border-0">
                            
                            <!-- Documento (Estilo ID) -->
                            <td class="py-4 px-6 font-medium text-gray-700">{{ $cliente->cli_ruc_ci }}</td>
                            
                            <!-- Cliente (Estilo Azul Destacado como en la imagen) -->
                            <td class="py-4 px-6">
                                <span class="font-bold text-blue-600 block">{{ $cliente->cli_nombre }} {{$cliente->cli_apellido }}</span>
                                @if($cliente->cli_es_mayorista)
                                    <span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded text-amber-700 bg-amber-100">⭐ Mayorista</span>
                                @endif
                            </td>
                            
                            <!-- Contacto (Texto Principal + Secundario) -->
                            <td class="py-4 px-6 font-semibold text-gray-800">
                                <span class="block">{{ $cliente->cli_telefono ?? '---' }}</span>
                                <span class="text-xs text-gray-500 font-normal">{{ $cliente->cli_email }}</span>
                            </td>
                            
                            <!-- Condición (Texto normal) -->
                            <td class="py-4 px-6 text-gray-600">
                                @if($cliente->cli_limite_credito > 0)
                                    <span class="font-bold text-gray-800 block">Gs. {{ number_format($cliente->cli_limite_credito, 0, ',', '.') }}</span>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Límite Crédito</span>
                                @else
                                    <span class="text-gray-500">Solo Contado</span>
                                @endif
                            </td>
                            
                            <!-- Acciones (Botones de colores suaves) -->
                            <td class="py-4 px-6 text-center">
                                <div class="flex items-center justify-center gap-3">
                                    <!-- Botón Editar (Fondo amarillo suave) -->
                                    <button @click="openEdit({{ json_encode($cliente) }})" class="p-2 bg-[#fef3c7] text-[#d97706] hover:bg-[#fde68a] rounded-lg transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    
                                    <!-- Botón Eliminar (Fondo rojo suave con X) -->
                                    <button type="button" onclick="confirmarEliminacionCliente({{ $cliente->cli_id }})" class="p-2 bg-[#fee2e2] text-[#dc2626] hover:bg-[#fecaca] rounded-lg transition-colors" title="Eliminar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                    <form id="delete-cliente-{{ $cliente->cli_id }}" action="{{ route('clientes.destroy', $cliente->cli_id) }}" method="POST" style="display: none;">
                                        @csrf @method('DELETE')
                                    </form>
                                </div>
                            </td>
                            
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center text-gray-500">No hay clientes registrados en el sistema.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <div class="p-4 border-t border-gray-100 bg-gray-50/50">
            {{ $clientes->links() }}
        </div>
    </div>

    <!-- Modal Formulario Mejorado -->
    <div x-show="openModal" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div x-transition.scale class="bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            
            <div class="flex justify-between items-center px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="bg-blue-100 text-blue-600 p-1.5 rounded-lg" x-text="editMode ? '✏️' : '👤'"></span>
                    <span x-text="editMode ? 'Editar Cliente' : 'Registrar Nuevo Cliente'"></span>
                </h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-red-500 transition-colors p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form :action="editMode ? '/clientes/' + cli_id : '{{ route('clientes.store') }}'" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editMode"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">RUC / Documento *</label>
                        <input type="text" name="cli_ruc_ci" x-model="cli_ruc_ci" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Teléfono</label>
                        <input type="text" name="cli_telefono" x-model="cli_telefono" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Nombre *</label>
                        <input type="text" name="cli_nombre" x-model="cli_nombre" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Apellido</label>
                        <input type="text" name="cli_apellido" x-model="cli_apellido" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Dirección Física</label>
                        <input type="text" name="cli_direccion" x-model="cli_direccion" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Correo Electrónico</label>
                        <input type="email" name="cli_email" x-model="cli_email" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase font-black text-gray-500 mb-1">Límite de Crédito (Gs.)</label>
                        <input type="number" name="cli_limite_credito" x-model="cli_limite_credito" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-transparent py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/10 p-4 rounded-xl border border-blue-100 dark:border-blue-800/50 flex items-center gap-3 mt-2">
                    <input type="checkbox" id="cli_es_mayorista" name="cli_es_mayorista" x-model="cli_es_mayorista" class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                    <label for="cli_es_mayorista" class="text-sm font-bold text-blue-800 dark:text-blue-300 cursor-pointer">Activar precios mayoristas para este cliente</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800 mt-6">
                    <button type="button" @click="openModal = false" class="px-5 py-2.5 font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition-colors" x-text="editMode ? 'Guardar Cambios' : 'Registrar Cliente'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmarEliminacionCliente(id) {
        // (Tu script de confirmación de SweetAlert2 actual se mantiene intacto aquí)
    }
</script>
@endsection