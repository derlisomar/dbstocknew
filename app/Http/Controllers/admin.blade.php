<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>dbstock - Panel de Control</title>
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
            <img x-show="!sidebarExpanded" src="{{ asset('img/logo_mini.png') }}" alt="Logo Mini" class="h-10 w-auto object-contain">
            
            <!-- Logo principal más grande y estético (expandido) -->
            <img x-show="sidebarExpanded" src="{{ asset('img/logo.png') }}" alt="dbstock" class="h-12 w-auto object-contain">
            
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

    @can('COBRANZAS_REGISTRAR')
    <a href="{{ route('cobranzas.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('cobranzas*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
         <span class="text-lg">💵</span>
        <span x-show="sidebarExpanded" class="whitespace-nowrap">Cobranzas y Créditos</span>
    </a>
    @endcan

    <!-- 3. Operaciones (Desplegable) -->
    @canany(['VENTAS_HISTORIAL','CLIENTES_CREDITO','REPORTES_VER','CATALOGO_GESTIONAR'])
    <div x-data="{ openOperaciones: {{ request()->is('operaciones*') ? 'true' : 'false' }} }">
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
            @can('CLIENTES_CREDITO')
            <a href="{{ route('operaciones.clientes_control') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('operaciones.clientes_control') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📊 Control de Clientes
            </a>
            @endcan
           @can('REPORTES_VER')
           <a href="{{ route('operaciones.reporte_abc') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('operaciones.reporte_abc') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📊 Análisis ABC y Rentabilidad
            </a>
           @endcan
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('promociones.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('promociones.*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                🎁 Promociones y Ofertas
            </a>
            @endcan
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('productos.control') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->routeIs('productos.control') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">
                📦 Control de Productos
            </a>
            @endcan
        </div>
    </div>
    @endcanany

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
            @can('CATALOGO_GESTIONAR')
            <a href="{{ route('proveedores.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('proveedores*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🚚 Proveedores</a>
            @endcan
            @can('CONFIG_GESTIONAR')
            <a href="{{ route('depositos.index') }}" class="flex items-center gap-3 rounded-lg py-2 px-3 text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 {{ request()->is('depositos*') ? 'text-blue-600 dark:text-white font-semibold' : 'text-gray-500' }}">🏭 Depósitos</a>
            @endcan
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
    @canany(['USUARIOS_GESTIONAR','CONFIG_GESTIONAR'])
    <div x-data="{ openConfig: {{ request()->is('usuarios*') ? 'true' : 'false' }} }">
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
                        
                        <!-- Buscador -->
                        <div class="hidden sm:block relative">
                            <input type="text" placeholder="Comando de búsqueda o escritura..." class="w-80 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 py-2 pl-10 pr-12 text-sm text-gray-800 dark:text-gray-200 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors duration-300">
                            <span class="absolute left-3 top-2.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <span class="absolute right-3 top-2.5 bg-gray-200 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs px-1.5 py-0.5 rounded">⌘K</span>
                        </div>
                    </div>

                    <!-- Lado Derecho: Utilidades y Perfil -->
                    <div class="flex items-center gap-4">
                        
                        <!-- Botón de Modo Oscuro / Claro (FUNCIONAL) -->
                        <button @click="darkMode = !darkMode; if(darkMode){ document.documentElement.classList.add('dark'); localStorage.theme = 'dark'; } else { document.documentElement.classList.remove('dark'); localStorage.theme = 'light'; }" class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-white transition shadow-sm">
                            <span x-show="!darkMode">🌙</span>
                            <span x-show="darkMode" style="display: none;">☀️</span>
                        </button>

                        <!-- Notificaciones -->
                        <div class="relative">
                            <span class="absolute top-0 right-0 z-1 h-2 w-2 rounded-full bg-red-500"></span>
                            <button class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-white shadow-sm">
                                🔔
                            </button>
                        </div>

                        <!-- Perfil de Usuario con Menú Desplegable -->
                        <div class="relative border-l border-gray-200 dark:border-gray-700 pl-4" x-data="{ profileOpen: false }">
                            <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false" class="flex items-center gap-3 focus:outline-none">
                                <div class="text-right hidden lg:block">
                                    <span class="block text-sm font-medium text-gray-800 dark:text-white">{{ Auth::user()->usu_nombre }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">Usuario Activo</span>
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ Auth::user()->usu_usuario }}@dbstock.com</p>
                                </div>
                                
                                <!-- Opciones -->
                                <ul class="py-2 border-b border-gray-200 dark:border-gray-800">
                                    <li><a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">👤 Editar perfil</a></li>
                                    <li><a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">⚙️ Configuraciones de la cuenta</a></li>
                                    <li><a href="#" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">ℹ️ Apoyo</a></li>
                                </ul>

                                <!-- Selector de Idiomas -->
                                <div class="relative py-2 px-4" x-data="{ langOpen: false }">
                                    <button @click="langOpen = !langOpen" @click.away="langOpen = false" class="flex items-center justify-between w-full text-sm text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition">
                                        <div class="flex items-center gap-3">
                                            🌐 Idioma
                                        </div>
                                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-1 rounded-md text-xs">
                                            Inglés 🇺🇸
                                        </div>
                                    </button>
                                    
                                    <!-- Submenú de idiomas -->
                                    <div x-show="langOpen" x-transition style="display: none;" class="absolute right-full top-0 mr-2 w-40 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#1c2434] shadow-lg py-2">
                                        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20">🇺🇸 Inglés</a>
                                        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">🇪🇸 Español</a>
                                        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">🇧🇷 Portugués</a>
                                    </div>
                                </div>
                                
                                <!-- Botón Desconectar -->
                                <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-md border border-gray-300 dark:border-gray-700 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                            desconectar
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </header>

        <main class="p-3 md:p-4 w-full bg-gray-100 dark:bg-[#111827] min-h-[calc(100vh-4rem)]">
            @yield('contenido')
        </main>

        </div>
    </div>
</body>
</html>