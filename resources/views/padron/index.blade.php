@extends('layouts.admin')

@section('contenido')
<div x-data="{
    modal: false, editando: false, importar: false, cargando: false,
    ruc: '', dv: '', nombre: '', estado: 'ACTIVO',
    action: '',
    nuevo() { this.editando = false; this.ruc = ''; this.dv = ''; this.nombre = ''; this.estado = 'ACTIVO'; this.action = '{{ route('padron.registros.store') }}'; this.modal = true; },
    editar(r) { this.editando = true; this.ruc = r.pad_ruc; this.dv = r.pad_dv || ''; this.nombre = r.pad_nombre; this.estado = r.pad_estado || ''; this.action = '{{ url('padron-ruc') }}/' + r.pad_ruc; this.modal = true; }
}" class="space-y-5">

    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">🪪 Padrón de RUC</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Registro de consulta (DNIT) que completa el nombre al cargar un cliente por RUC. No son clientes.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="importar = true" class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 px-4 py-2.5 rounded-lg font-medium text-sm hover:bg-gray-50 dark:hover:bg-gray-700">📥 Importar archivos</button>
            <button type="button" @click="nuevo()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-medium transition shadow-lg">➕ Agregar manualmente</button>
        </div>
    </div>

    @if(session('padron_ok'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('padron_ok') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="GET" action="{{ route('padron.registros.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        <div class="md:col-span-2">
            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-0.5">Buscar por RUC o nombre</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Ej: 1037415  o  perez juan" class="w-full rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
        </div>
        <div>
            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-0.5">Estado</label>
            <select name="estado" class="w-full rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 py-2 px-3 text-sm text-gray-800 dark:text-white">
                <option value="">Todos</option>
                @foreach($estados as $e)<option value="{{ $e }}" @selected($estado === $e)>{{ ucfirst(mb_strtolower($e)) }}</option>@endforeach
                <option value="OTRO" @selected($estado === 'OTRO')>Sin estado / otros</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm">Filtrar</button>
            <a href="{{ route('padron.registros.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-white">Limpiar</a>
        </div>
    </form>

    <div class="bg-white dark:bg-[#1c2434] border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
        <div class="px-5 py-3 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">{{ number_format($registros->total(), 0, ',', '.') }} registros</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-gray-500 dark:text-gray-400 text-xs uppercase bg-gray-50 dark:bg-gray-900/50">
                        <th class="py-3 px-5">RUC</th><th class="py-3 px-5">Nombre / Razón social</th><th class="py-3 px-5">Estado</th><th class="py-3 px-5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                @forelse($registros as $r)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <td class="py-3 px-5 font-semibold whitespace-nowrap">{{ $r->pad_ruc }}{{ $r->pad_dv !== null ? '-'.$r->pad_dv : '' }}</td>
                        <td class="py-3 px-5">{{ $r->pad_nombre }}</td>
                        <td class="py-3 px-5">
                            @php $color = match($r->pad_estado) { 'ACTIVO' => 'bg-emerald-100 text-emerald-700', 'SUSPENSION TEMPORAL' => 'bg-amber-100 text-amber-700', 'CANCELADO', 'BLOQUEADO' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-600' }; @endphp
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $color }}">{{ $r->pad_estado ?? '—' }}</span>
                        </td>
                        <td class="py-3 px-5">
                          <div class="flex flex-row items-center justify-center gap-2">
                            <button type="button" @click="editar(@js($r->only('pad_ruc', 'pad_dv', 'pad_nombre', 'pad_estado')))" class="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100" title="Editar">✏️</button>
                            <form method="POST" action="{{ route('padron.registros.destroy', $r->pad_ruc) }}" class="m-0 inline-flex" onsubmit="return confirm('¿Eliminar este RUC del padrón?')">
                                @csrf @method('DELETE')
                                <button class="px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100" title="Eliminar">✖</button>
                            </form>
                          </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-gray-400">No hay registros con ese filtro. Si todavía no cargaste el padrón, usá "Importar archivos".</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-800">{{ $registros->links() }}</div>
    </div>

    <!-- Agregar / editar -->
    <div x-show="modal" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div @click.away="modal = false" class="bg-white dark:bg-[#1c2434] w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="editando ? 'Editar registro' : 'Agregar al padrón'"></h3>
                <button type="button" @click="modal = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            <form method="POST" :action="action" class="p-6 space-y-4">
                @csrf
                <template x-if="editando"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="text-[11px] uppercase font-bold text-gray-500 block mb-1">RUC *</label>
                        <input type="text" name="pad_ruc" x-model="ruc" :readonly="editando" :disabled="editando" required maxlength="13" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm text-gray-800 dark:text-white disabled:opacity-60">
                    </div>
                    <div>
                        <label class="text-[11px] uppercase font-bold text-gray-500 block mb-1">DV</label>
                        <input type="text" name="pad_dv" x-model="dv" maxlength="1" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm text-gray-800 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="text-[11px] uppercase font-bold text-gray-500 block mb-1">Nombre o razón social *</label>
                    <input type="text" name="pad_nombre" x-model="nombre" required maxlength="255" placeholder="APELLIDOS, NOMBRES  (personas)" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="text-[11px] uppercase font-bold text-gray-500 block mb-1">Estado</label>
                    <select name="pad_estado" x-model="estado" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent py-2 px-3 text-sm text-gray-800 dark:text-white">
                        <option value="">Sin estado</option>
                        @foreach($estados as $e)<option value="{{ $e }}">{{ ucfirst(mb_strtolower($e)) }}</option>@endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="modal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Importar archivos -->
    <div x-show="importar" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div @click.away="if(!cargando) importar = false" class="bg-white dark:bg-[#1c2434] w-full max-w-lg rounded-xl shadow-2xl overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">📥 Importar padrón de RUC (DNIT)</h3>
                <button type="button" @click="importar = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white text-xl font-bold">&times;</button>
            </div>
            <form method="POST" action="{{ route('padron.importar') }}" enctype="multipart/form-data" @submit="cargando = true" class="p-6 space-y-4">
                @csrf
                <p class="text-sm text-gray-600 dark:text-gray-300">Elegí los archivos <b>ruc0 a ruc9</b> del <a class="text-blue-600 underline" target="_blank" rel="noopener" href="https://www.dnit.gov.py/web/portal-institucional/listado-de-ruc-con-sus-equivalencias">listado de la DNIT</a> (.txt, .csv o .zip). Si ya existen, se actualizan sin duplicar.</p>
                <input type="file" name="archivos[]" multiple accept=".txt,.csv,.zip" required class="block w-full text-sm text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 rounded-lg p-2">
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="importar = false" :disabled="cargando" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg">Cancelar</button>
                    <button type="submit" :disabled="cargando" class="px-5 py-2 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700 disabled:opacity-60" x-text="cargando ? 'Importando… no cierres la página' : 'Importar'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
