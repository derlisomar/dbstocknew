@extends('layouts.admin')

@section('contenido')
<div class="space-y-6">
    
    <!-- Cabecera Global y Filtro de Sucursal -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-[#1c2434] p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
        <div>
            <h1 class="text-xl font-black text-gray-800 dark:text-white flex items-center gap-2">
                🛡️ Control Operacional de Inventario
            </h1>
            <p class="text-xs text-gray-500 mt-1">Supervisa niveles de stock, ventas top y gestiona el catálogo global.</p>
        </div>
        
        <form method="GET" action="{{ route('productos.control') }}" class="flex shrink-0">
            <!-- Mantenemos las búsquedas activas al cambiar de sucursal -->
            <input type="hidden" name="buscar_gen" value="{{ request('buscar_gen') }}">
            <input type="hidden" name="buscar_out" value="{{ request('buscar_out') }}">
            <input type="hidden" name="buscar_top" value="{{ request('buscar_top') }}">
            
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">🏢</span>
                <select name="suc_id" onchange="this.form.submit()" class="pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer transition-all appearance-none">
                    <option value="">Todas las Sucursales</option>
                    @foreach($sucursales as $suc)
                        <option value="{{ $suc->suc_id }}" {{ $sucursal_id == $suc->suc_id ? 'selected' : '' }}>
                            {{ $suc->suc_nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <!-- GRID PRINCIPAL: 50% - 25% - 25% (12 Columnas en Tailwind) -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 h-[calc(100vh-12rem)]">
        
        <!-- ================= 50%: CATÁLOGO GENERAL ================= -->
        <div class="xl:col-span-6 flex flex-col bg-white dark:bg-[#1c2434] border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden h-full relative">
            <div class="p-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/20 flex justify-between items-center gap-4">
                <h2 class="text-sm font-black text-gray-800 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <span class="text-blue-500">📦</span> Catálogo
                </h2>
                <!-- Buscador 1 -->
                <form method="GET" action="{{ route('productos.control') }}" class="relative w-1/2">
                    <input type="hidden" name="suc_id" value="{{ request('suc_id') }}">
                    <input type="text" name="buscar_gen" value="{{ request('buscar_gen') }}" placeholder="Buscar producto..." class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-xs outline-none focus:border-blue-500 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400">🔍</span>
                </form>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
                <table class="w-full text-left text-xs">
                    <thead class="text-[10px] text-gray-400 uppercase font-bold sticky top-0 bg-white dark:bg-[#1c2434] z-10">
                        <tr>
                            <th class="py-2 px-3">Producto</th>
                            <th class="py-2 px-3 text-center">Stock</th>
                            <th class="py-2 px-3 text-right">Precio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                        @forelse($productosGral as $pro)
                        <tr class="hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors">
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-gray-800 dark:text-gray-200 line-clamp-1">{{ $pro->pro_nombre }}</div>
                                <div class="text-[10px] text-gray-400">{{ $pro->pro_codigo }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $pro->pro_stockactual > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $pro->pro_stockactual }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-gray-700 dark:text-gray-300">
                                Gs. {{ number_format($pro->pro_precioventa, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-8 text-gray-400 text-xs">No hay productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Paginación General -->
            <div class="p-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30 scale-90 origin-bottom">
                {{ $productosGral->links() }}
            </div>
        </div>

        <!-- ================= 25%: SIN STOCK ================= -->
        <div class="xl:col-span-3 flex flex-col bg-white dark:bg-[#1c2434] border border-red-100 dark:border-red-900/30 rounded-2xl shadow-sm overflow-hidden h-full relative">
            <div class="p-4 border-b border-red-100 dark:border-red-900/30 bg-red-50/30 dark:bg-red-900/10 flex flex-col gap-3">
                <h2 class="text-sm font-black text-red-600 dark:text-red-400 uppercase tracking-wider flex items-center gap-2">
                    <span>⚠️</span> Sin Stock
                </h2>
                <!-- Buscador 2 -->
                <form method="GET" action="{{ route('productos.control') }}" class="relative w-full">
                    <input type="hidden" name="suc_id" value="{{ request('suc_id') }}">
                    <input type="text" name="buscar_out" value="{{ request('buscar_out') }}" placeholder="Buscar agotados..." class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-gray-900 border border-red-200 dark:border-red-800 rounded-lg text-xs outline-none focus:border-red-500 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-red-400">🔍</span>
                </form>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
                <ul class="space-y-1">
                    @forelse($productosSinStock as $pro)
                    <li class="p-2 hover:bg-red-50/50 dark:hover:bg-red-900/20 rounded-lg transition-colors border border-transparent hover:border-red-100 dark:hover:border-red-900/50">
                        <div class="font-bold text-xs text-gray-800 dark:text-gray-200 line-clamp-1">{{ $pro->pro_nombre }}</div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-[9px] text-gray-400">{{ $pro->pro_codigo }}</span>
                            <span class="text-[10px] font-black text-red-500">Agotado</span>
                        </div>
                    </li>
                    @empty
                    <li class="text-center py-8 text-gray-400 text-xs">Todo el stock está óptimo ✨</li>
                    @endforelse
                </ul>
            </div>
            <!-- Paginación Sin Stock -->
            <div class="p-3 border-t border-red-100 dark:border-red-900/30 bg-red-50/20 dark:bg-red-900/10 scale-90 origin-bottom">
                {{ $productosSinStock->links() }}
            </div>
        </div>

        <!-- ================= 25%: MÁS VENDIDOS & KPIs ================= -->
        <div class="xl:col-span-3 flex flex-col bg-white dark:bg-[#1c2434] border border-amber-100 dark:border-amber-900/30 rounded-2xl shadow-sm overflow-hidden h-full relative">
            
            <div class="p-4 border-b border-amber-100 dark:border-amber-900/30 bg-amber-50/30 dark:bg-amber-900/10">
                <h2 class="text-sm font-black text-amber-600 dark:text-amber-500 uppercase tracking-wider flex items-center gap-2 mb-3">
                    <span>🔥</span> Más Vendidos
                </h2>
                
                <!-- Buscador 3 -->
                <form method="GET" action="{{ route('productos.control') }}" class="relative w-full mb-4">
                    <input type="hidden" name="suc_id" value="{{ request('suc_id') }}">
                    <input type="text" name="buscar_top" value="{{ request('buscar_top') }}" placeholder="Buscar en top ventas..." class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800 rounded-lg text-xs outline-none focus:border-amber-500 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-amber-400">🔍</span>
                </form>

                <!-- Mini KPI -->
                @if($productosMasVendidos->first())
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl p-3 text-white shadow-md shadow-orange-500/20">
                    <p class="text-[9px] uppercase font-black tracking-wider opacity-80 mb-0.5">Producto Estrella 🏆</p>
                    <p class="text-xs font-bold line-clamp-1">{{ $productosMasVendidos->first()->pro_nombre }}</p>
                    <p class="text-sm font-black mt-1">{{ $productosMasVendidos->first()->total_vendido }} unid. vendidas</p>
                </div>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
                <ul class="space-y-1">
                    @foreach($productosMasVendidos as $index => $pro)
                    <li class="p-2 flex items-center gap-3 hover:bg-amber-50/40 dark:hover:bg-amber-900/20 rounded-lg transition-colors border border-transparent hover:border-amber-100 dark:hover:border-amber-900/50">
                        <div class="w-6 h-6 rounded bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 flex items-center justify-center text-[10px] font-black shrink-0">
                            #{{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-xs text-gray-800 dark:text-gray-200 line-clamp-1">{{ $pro->pro_nombre }}</div>
                            <div class="text-[10px] text-gray-400 font-medium">Stock: {{ $pro->pro_stockactual }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-black text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/30 px-2 py-1 rounded-md">
                                {{ $pro->total_vendido ?? 0 }} u.
                            </span>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            
        </div>
    </div>
</div>
@endsection