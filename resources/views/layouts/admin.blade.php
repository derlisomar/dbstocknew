<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\ConfiguracionService::nombreNegocio() }} - Panel de Control</title>
    <!-- Google Fonts Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style> body { font-family: 'Outfit', sans-serif; } </style>
    
    <!-- Script Anti-Parpadeo para Modo Oscuro/Claro -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>[x-cloak]{display:none !important}</style>
</head>

<!-- Alpine.js Global Data -->
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 antialiased transition-colors duration-300" 
      x-data="{ sidebarExpanded: true, darkMode: document.documentElement.classList.contains('dark') }">

    <div class="flex h-screen overflow-hidden">

        <!-- ================= SIDEBAR DINÁMICO (Achicable) ================= -->
      <aside :class="sidebarExpanded ? 'w-72' : 'w-20'" class="relative flex h-screen flex-col bg-white dark:bg-[#1c2434] transition-all duration-300 ease-in-out border-r border-gray-200 dark:border-gray-800 z-50 overflow-y-auto overflow-x-hidden">
        <!-- Logo Sidebar -->
        <div class="flex items-center justify-center gap-2 px-4 py-5.5 lg:py-6.5 h-24">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 overflow-hidden">
            
            <!-- Logo pequeño (colapsado) -->
            <img x-show="!sidebarExpanded" src="{{ \App\Services\ConfiguracionService::logoUrl() ?? asset('img/logo_mini.png') }}" alt="Logo Mini" class="h-10 w-auto object-contain">
            
            <!-- Logo principal más grande y estético (expandido) -->
            @php $logoPropio = \App\Services\ConfiguracionService::logoUrl(); @endphp
            @if($logoPropio)
                <img x-show="sidebarExpanded" src="{{ $logoPropio }}" alt="{{ \App\Services\ConfiguracionService::nombreNegocio() }}" class="h-12 w-auto object-contain">
            @else
                {{-- Logo por defecto: versión a color en modo claro y versión blanca en modo oscuro --}}
                <style>.logo-def-oscuro{display:none}html.dark .logo-def-claro{display:none}html.dark .logo-def-oscuro{display:block}</style>
                <img x-show="sidebarExpanded" src="{{ asset('img/logo-claro.png') }}" alt="{{ \App\Services\ConfiguracionService::nombreNegocio() }}" class="logo-def-claro h-12 w-auto object-contain">
                <img x-show="sidebarExpanded" src="{{ asset('img/logo.png') }}" alt="" class="logo-def-oscuro h-12 w-auto object-contain">
            @endif
            
            </a>
        </div>
<!-- Enlaces del Menú Lateral Organizados -->
<div class="space-y-1 px-4 py-4">

    <!-- 1. Dashboard -->
    <a href="{{ route('dashboard') }}" class="flex items-center gap-4 rounded-lg py-3 px-3 text-sm font-medium duration-300 hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('dashboard*') ? 'bg-gray-100 dark:bg-gray-800 text-blue-600 dark:text-white border-l-4 border-blue-600' : 'text-gray-600 dark:text-gray-400' }}">
        <span class="text-lg">🏠</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Dashboard</span>
    </a>

    <!-- 2. Punto de Venta (PDV) -->
    @can('PDV_USAR')
    <a href="{{ route('pdv.index') }}" class="flex items-center gap-4 rounded-lg py-3 px-3 text-sm font-medium duration-300 hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('pdv*') ? 'bg-gray-100 dark:bg-gray-800 text-blue-600 dark:text-white border-l-4 border-blue-600' : 'text-gray-600 dark:text-gray-400' }}">
        <span class="text-lg">🛒</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Punto de Venta (PDV)</span>
    </a>
    @endcan

    @modulo('presupuestos')
    @canany(['PRESUPUESTOS_GESTIONAR','PDV_USAR'])
    <a href="{{ route('presupuestos.index') }}" class="flex items-center gap-4 rounded-lg py-3 px-3 text-sm font-medium duration-300 hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('presupuestos*') ? 'bg-gray-100 dark:bg-gray-800 text-blue-600 dark:text-white border-l-4 border-blue-600' : 'text-gray-600 dark:text-gray-400' }}">
        <span class="text-lg">📝</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Presupuestos</span>
    </a>
    @endcanany
    @endmodulo

    @modulo('cobranzas')
    @can('COBRANZAS_REGISTRAR')
    <a href="{{ route('cobranzas.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('cobranzas*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
         <span class="text-lg">💵</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Cobranzas y Créditos</span>
    </a>
    <a href="{{ route('cobranzas.historial') }}" class="flex items-center gap-4 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('cobranzas.historial') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
        <span class="text-lg">🧾</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Historial de Cobros</span>
    </a>
    @endcan
    @endmodulo

    <!-- 3. Operaciones (Desplegable) -->
    @canany(['VENTAS_HISTORIAL','CLIENTES_CREDITO','REPORTES_VER','CATALOGO_GESTIONAR','STOCK_AJUSTAR'])
    <div x-data="{ openOperaciones: {{ request()->is('operaciones*', 'inventario*') ? 'true' : 'false' }} }">
        <button @click="openOperaciones = !openOperaciones" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">📈</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Operaciones</span>
            </div>
            <span x-show="sidebarExpanded" :class="openOperaciones ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        
        <div x-show="openOperaciones && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            @can('VENTAS_HISTORIAL')
            <a href="{{ route('operaciones.ventas') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('operaciones/ventas*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📜 Control de Ventas</a>
            @endcan
            @modulo('cobranzas')
            @can('CLIENTES_CREDITO')
            <a href="{{ route('operaciones.clientes_control') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('operaciones.clientes_control') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📊 Control de Clientes
            </a>
            @endcan
            @endmodulo
           @modulo('reportes_avanzados')
           @can('REPORTES_VER')
           <a href="{{ route('operaciones.reporte_abc') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('operaciones.reporte_abc') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📊 Análisis ABC y Rentabilidad
            </a>
           @endcan
           @endmodulo
            @modulo('promociones')
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('promociones.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('promociones.*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🎁 Promociones y Ofertas
            </a>
            @endcan
            @endmodulo
            @modulo('inventario')
            @canany(['CATALOGO_GESTIONAR','STOCK_AJUSTAR'])
            <a href="{{ route('inventario.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('inventario.*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🗃️ Inventario y Stock
            </a>
            @endcanany
            @endmodulo
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('productos.control') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('productos.control') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📦 Control de Productos
            </a>
            @endcan
        </div>
    </div>
    @endcanany

    <!-- Compras y proveedores (Desplegable) -->
    @modulo('compras')
    @canany(['COMPRAS_REGISTRAR','COMPRAS_ANULAR','PAGOS_PROVEEDORES'])
    <div x-data="{ openCompras: {{ request()->is('compras*', 'cuentas-pagar*') ? 'true' : 'false' }} }">
        <button @click="openCompras = !openCompras" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">🚚</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Compras y Proveedores</span>
            </div>
            <span x-show="sidebarExpanded" :class="openCompras ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        <div x-show="openCompras && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            @can('COMPRAS_REGISTRAR')
            <a href="{{ route('compras.create') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('compras.create') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">➕ Registrar compra</a>
            @endcan
            <a href="{{ route('compras.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('compras.index', 'compras.show') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🧾 Listado de compras</a>
            @canany(['PAGOS_PROVEEDORES','COMPRAS_REGISTRAR','COMPRAS_ANULAR'])
            <a href="{{ route('cuentas_pagar.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('cuentas_pagar.*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📒 Cuentas a pagar</a>
            @endcanany
        </div>
    </div>
    @endcanany
    @endmodulo

    <!-- Contabilidad (solo edición completa) -->
    @modulo('contabilidad')
    @canany(['CONTABILIDAD_VER','CONTABILIDAD_GESTIONAR'])
    <div x-data="{ openConta: {{ request()->is('contabilidad*') ? 'true' : 'false' }} }">
        <button @click="openConta = !openConta" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">📚</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Contabilidad</span>
            </div>
            <span x-show="sidebarExpanded" :class="openConta ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        <div x-show="openConta && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            @can('CONTABILIDAD_VER')
            <a href="{{ route('contabilidad.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('contabilidad.index') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📊 Panel contable</a>
            <a href="{{ route('contabilidad.diario') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('contabilidad.diario') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📖 Libro diario</a>
            <a href="{{ route('contabilidad.balance') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('contabilidad.balance', 'contabilidad.resultados', 'contabilidad.sumas', 'contabilidad.mayor') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">⚖️ Balances y mayor</a>
            <a href="{{ route('contabilidad.iva', 'ventas') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('contabilidad.iva') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🧾 Libro IVA</a>
            @endcan
            @can('CONTABILIDAD_GESTIONAR')
            <a href="{{ route('contabilidad.asiento.nuevo') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('contabilidad.asiento.*', 'contabilidad.mapeos', 'contabilidad.plan') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">✍️ Ajustes y mapeos</a>
            @endcan
        </div>
    </div>
    @endcanany
    @endmodulo

    <!-- 3. Finanzas y Cajas (Desplegable) -->
    @canany(['FINANZAS_VER','CAJA_ABRIR_CERRAR'])
    <div x-data="{ openFinanzas: {{ request()->is('finanzas*') ? 'true' : 'false' }} }">
        <button @click="openFinanzas = !openFinanzas" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">💰</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Finanzas y Cajas</span>
            </div>
            <span x-show="sidebarExpanded" :class="openFinanzas ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        
        <div x-show="openFinanzas && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            
            @canany(['FINANZAS_VER','CAJA_ABRIR_CERRAR'])
            <a href="{{ route('finanzas.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('finanzas') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📊 Estado de Cajas</a>
            @endcanany
            @can('FINANZAS_VER')
            <a href="{{ route('finanzas.movimientos') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('finanzas/movimientos*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">💸 Movimientos / Transferencias</a>
            @endcan
            @can('FINANZAS_VER')
            <a href="{{ route('finanzas.cierres') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('finanzas/cierres*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🔐 Historial de Cierres</a>
            @endcan
            @can('FINANZAS_VER')
            <a href="{{ route('finanzas.ingresos_egresos') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('finanzas.ingresos_egresos') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                ⚖️ Ingresos y Egresos
            </a>
            @endcan
        </div>
    </div>
    @endcanany

    <!-- 4. Registros y Catálogos (Desplegable) -->
    @canany(['CLIENTES_GESTIONAR','CATALOGO_GESTIONAR','CONFIG_GESTIONAR'])
    <div x-data="{ openCatalogos: {{ request()->is('clientes*') || request()->is('productos*') || request()->is('categorias*') || request()->is('proveedores*') || request()->is('depositos*') || request()->is('sucursales*') || request()->is('cajas*') ? 'true' : 'false' }} }">
        <button @click="openCatalogos = !openCatalogos" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">📁</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Registros y Catálogos</span>
            </div>
            <span x-show="sidebarExpanded" :class="openCatalogos ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        
        <div x-show="openCatalogos && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            @can('CLIENTES_GESTIONAR')
            <a href="{{ route('clientes.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('clientes*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">👥 Clientes</a>
            @endcan
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('productos.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('productos*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">📦 Productos</a>
            @endcan
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('categorias.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('categorias*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🏷️ Categorías</a>
            @endcan
            @modulo('compras')
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('proveedores.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('proveedores*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🚚 Proveedores</a>
            @endcan
            @endmodulo
            @modulo('depositos')
            @can('CONFIG_GESTIONAR')
            <a href="{{ route('depositos.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('depositos*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🏭 Depósitos</a>
            @endcan
            @endmodulo
            @can('CONFIG_GESTIONAR')
            <a href="{{ route('sucursales.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('sucursales*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🏢 Sucursales</a>
            @endcan
            @can('CONFIG_GESTIONAR')
            <a href="{{ route('cajas.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('cajas*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🧮 Cajas Físicas</a>
            @endcan
        </div>
    </div>
    @endcanany

    <!-- 5. Configuración y Administración (Desplegable) -->
    @canany(['USUARIOS_GESTIONAR','CONFIG_GESTIONAR','AUDITORIA_VER'])
    <div x-data="{ openConfig: {{ request()->is('usuarios*', 'auditoria*') ? 'true' : 'false' }} }">
        <button @click="openConfig = !openConfig" class="flex items-center justify-between w-full rounded-lg py-3 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-4">
                <span class="text-lg">⚙️</span>
                <span x-show="sidebarExpanded" class="whitespace-nowrap">Config. y Administración</span>
            </div>
            <span x-show="sidebarExpanded" :class="openConfig ? 'rotate-180' : ''" class="transition-transform duration-200 text-xs">▼</span>
        </button>
        
        <div x-show="openConfig && sidebarExpanded" class="pl-8 mt-1 space-y-1" style="display: none;">
            @can('USUARIOS_GESTIONAR')
            <a href="{{ route('usuarios.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('usuarios*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">👤 Gestión de Usuarios</a>
            @endcan
            
            @can('USUARIOS_GESTIONAR')
            <a href="{{ route('roles.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('roles*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🛡️ Gestión de Roles
            </a>
            @endcan

            @modulo('auditoria')
            @can('AUDITORIA_VER')
            <a href="{{ route('auditoria.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('auditoria*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🕵️ Auditoría
            </a>
            @endcan
            @endmodulo

            @can('CONFIG_GESTIONAR')
            <a href="{{ route('cotizaciones.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('cotizaciones*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
            💱 Cotizaciones de Moneda
            </a>
            @endcan
            @can('CONFIG_GESTIONAR')
            <a href="{{ route('configuracion.terminal') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('configuracion.terminal') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🖥️ Configuracion Equipo
            </a>
            @endcan
        
        </div>
 
    </div>
    @endcanany

</div>

        </aside>

        <!-- ================= CONTENEDOR CENTRAL ================= -->
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            
            <!-- Header Superior -->
            <header class="sticky top-0 z-40 flex w-full bg-white dark:bg-[#1c2434] border-b border-gray-200 dark:border-gray-800 drop-shadow-sm transition-colors duration-300">
                <div class="flex flex-grow items-center justify-between px-4 py-3 md:px-6 2xl:px-11">
                    
                    <!-- Lado Izquierdo: Botón Menú y Buscador -->
                    <div class="flex items-center gap-4">
                        <!-- Botón Hamburguesa para achicar sidebar -->
                        <button @click="sidebarExpanded = !sidebarExpanded" class="p-2 border border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>
                        
                        <!-- Buscador de pantallas (Ctrl+K o /) -->
                        <div class="hidden sm:block relative" id="bg-caja">
                            <input type="text" id="bg-input" autocomplete="off" placeholder="Buscar una pantalla (ventas, clientes, caja...)" aria-label="Buscar una pantalla del sistema" class="w-80 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 py-2 pl-10 pr-14 text-sm text-gray-800 dark:text-gray-200 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors duration-300">
                            <span class="absolute left-3 top-2.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <span class="absolute right-3 top-2.5 bg-gray-200 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs px-1.5 py-0.5 rounded">Ctrl K</span>
                            <div id="bg-res" role="listbox" hidden></div>
                        </div>
                    </div>

                    <!-- Lado Derecho: Utilidades y Perfil -->
                    <div class="flex items-center gap-4">
                        
                        <!-- Botón de Modo Oscuro / Claro (FUNCIONAL) -->
                        <button @click="darkMode = !darkMode; if(darkMode){ document.documentElement.classList.add('dark'); localStorage.theme = 'dark'; } else { document.documentElement.classList.remove('dark'); localStorage.theme = 'light'; }" class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-white transition shadow-sm">
                            <span x-show="!darkMode">🌙</span>
                            <span x-show="darkMode" style="display: none;">☀️</span>
                        </button>

                        <!-- Notificaciones (avisos reales: stock, deudas, presupuestos, plan) -->
                        <div class="relative" x-data="{
                                open: false, total: 0, items: [], cargado: false,
                                cargar() { fetch('{{ route('notificaciones.index') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                                    .then(r => r.ok ? r.json() : { total: 0, items: [] })
                                    .then(d => { this.total = d.total; this.items = d.items; this.cargado = true; })
                                    .catch(() => { this.cargado = true; }); }
                             }" x-init="cargar()">
                            <span x-show="total > 0" style="display: none;" class="absolute top-0 right-0 z-1 h-2 w-2 rounded-full bg-red-500"></span>
                            <button @click="open = !open; if (open) cargar()" @click.away="open = false" class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-white shadow-sm" aria-label="Notificaciones">
                                🔔
                            </button>
                            <div x-show="open" x-transition style="display: none; width: 340px; max-width: calc(100vw - 24px);" class="absolute right-0 mt-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#1c2434] shadow-xl z-50">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                                    <p class="text-sm font-bold text-gray-800 dark:text-white">Notificaciones</p>
                                </div>
                                <div style="max-height: 380px; overflow-y: auto;">
                                    <template x-if="cargado && items.length === 0">
                                        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400" style="text-align:center">Todo en orden. No hay avisos por ahora.</p>
                                    </template>
                                    <template x-for="n in items" :key="n.titulo">
                                        <a :href="n.url" :style="n.url ? '' : 'cursor: default'" class="block px-4 py-3 border-b border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                            <p class="text-sm font-semibold text-gray-800 dark:text-white" x-text="(n.nivel === 'critico' ? '🔴 ' : (n.nivel === 'aviso' ? '🟡 ' : '🔵 ')) + n.titulo"></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="n.detalle"></p>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Perfil de Usuario con Menú Desplegable -->
                        <div class="relative border-l border-gray-200 dark:border-gray-700 pl-4" x-data="{ profileOpen: false }">
                            <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false" class="flex items-center gap-3 focus:outline-none">
                                <div class="text-right hidden lg:block">
                                    <span class="block text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->usu_nombre }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->rol?->rol_nombre ?? 'Usuario' }}</span>
                                </div>
                                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center font-bold text-white shadow-md">
                                    {{ substr(Auth::user()->usu_nombre, 0, 1) }}
                                </div>
                                <svg class="hidden sm:block w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <!-- Dropdown del Perfil -->
                            <div x-show="profileOpen" x-transition style="display: none;" class="absolute right-0 mt-3.5 w-64 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#1c2434] shadow-xl z-50">
                                
                                <!-- Cabecera del Dropdown -->
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                                    <p class="text-sm font-bold text-gray-800 dark:text-white">{{ Auth::user()->usu_nombre }} {{ Auth::user()->usu_apellido }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ Auth::user()->usu_email ?: Auth::user()->usu_usuario }}</p>
                                </div>
                                
                                <!-- Opciones -->
                                <ul class="py-2 border-b border-gray-200 dark:border-gray-800">
                                    <li><a href="{{ route('perfil.edit') }}#datos" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">👤 Editar perfil</a></li>
                                    <li><a href="{{ route('perfil.edit') }}#clave" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">⚙️ Configuraciones de la cuenta</a></li>
                                </ul>

                                <!-- Botón Desconectar -->
                                <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-md border border-gray-300 dark:border-gray-700 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                            Cerrar sesión
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </header>

        <main class="p-3 md:p-4 w-full bg-gray-100 dark:bg-[#111827] min-h-[calc(100vh-4rem)]">
            {{-- Avisos generales (las pantallas de ventas y sucursales ya muestran los suyos) --}}
            @php $licencia = \App\Services\LicenciaService::estado(); @endphp
            @if($licencia['mensaje'])
                <div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-weight:600;font-size:14px;{{ $licencia['estado'] === 'SOLO_LECTURA' ? 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5' : 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d' }}">{{ $licencia['mensaje'] }}</div>
            @endif
            @if(session('error') && !request()->routeIs('operaciones.ventas', 'sucursales.*'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-900/30 dark:text-red-300">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-900/30 dark:text-amber-300">{{ session('warning') }}</div>
            @endif
            @yield('contenido')
        </main>

        </div>
    </div>
<style>
#bg-res{position:absolute;left:0;top:calc(100% + 6px);width:min(26rem,90vw);max-height:22rem;overflow-y:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 20px 40px -12px rgba(0,0,0,.35);z-index:80;padding:6px}
html.dark #bg-res{background:#1c2434;border-color:#2e3a47}
#bg-res a{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:9px 12px;border-radius:8px;text-decoration:none;color:#1f2937;font-size:14px}
html.dark #bg-res a{color:#e5e7eb}
#bg-res a small{color:#9ca3af;font-size:12px;white-space:nowrap}
#bg-res a.on,#bg-res a:hover{background:#eff6ff;color:#1d4ed8}
html.dark #bg-res a.on,html.dark #bg-res a:hover{background:#24303f;color:#93c5fd}
#bg-res .bg-vacio{padding:14px;text-align:center;color:#9ca3af;font-size:13px}
</style>
<script>
(function () {
    var inp = document.getElementById('bg-input'), res = document.getElementById('bg-res');
    if (!inp || !res) return;
    var norm = function (t) { return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
    var items = null, activo = -1, vista = [];
    function texto(el) {
        var c = el.cloneNode(true);
        c.querySelectorAll('style,script,svg,img,template').forEach(function (n) { n.remove(); });
        return c.textContent.replace(/[▼▲]/g, '').replace(/\s+/g, ' ').trim();
    }
    function cargar() {
        if (items) return items;
        var vistos = {}; items = [];
        document.querySelectorAll('aside a[href]').forEach(function (a) {
            var href = a.getAttribute('href'), txt = texto(a);
            if (!href || href === '#' || href.indexOf('javascript') === 0 || !txt || vistos[href]) return;
            vistos[href] = 1;
            var grupo = '', cont = a.closest('div[x-data]');
            if (cont) { var b = cont.querySelector('button'); if (b) grupo = texto(b); }
            items.push({ href: href, txt: txt, grupo: grupo, n: norm(txt + ' ' + grupo) });
        });
        return items;
    }
    function pintar() {
        res.innerHTML = '';
        if (!vista.length) { res.innerHTML = '<div class="bg-vacio">No hay pantallas con ese nombre.</div>'; return; }
        vista.forEach(function (it, i) {
            var a = document.createElement('a'); a.href = it.href; a.setAttribute('role', 'option');
            if (i === activo) a.className = 'on';
            var t = document.createElement('span'); t.textContent = it.txt; a.appendChild(t);
            if (it.grupo) { var g = document.createElement('small'); g.textContent = it.grupo; a.appendChild(g); }
            res.appendChild(a);
        });
    }
    function buscar() {
        var q = norm(inp.value.trim()), lista = cargar();
        var palabras = q.split(' ').filter(Boolean);
        vista = lista.filter(function (it) { return palabras.every(function (p) { return it.n.indexOf(p) !== -1; }); }).slice(0, 12);
        activo = vista.length ? 0 : -1;
        res.hidden = false; pintar();
    }
    function cerrar() { res.hidden = true; activo = -1; }
    inp.addEventListener('focus', buscar);
    inp.addEventListener('input', buscar);
    inp.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault(); if (!vista.length) return;
            activo = (activo + (e.key === 'ArrowDown' ? 1 : -1) + vista.length) % vista.length; pintar();
            var on = res.querySelector('a.on'); if (on) on.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault(); if (vista[activo]) window.location.href = vista[activo].href;
        } else if (e.key === 'Escape') { cerrar(); inp.blur(); }
    });
    document.addEventListener('keydown', function (e) {
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        var escribiendo = /INPUT|TEXTAREA|SELECT/.test(tag) || (document.activeElement && document.activeElement.isContentEditable);
        if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !escribiendo)) {
            e.preventDefault(); inp.focus(); inp.select();
        }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('#bg-caja')) cerrar(); });
})();
</script>
</body>
</html>