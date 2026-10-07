<!DOCTYPE html>
<html lang="es" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) }" x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - dbstock</title>
    <!-- Google Fonts Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Estilos de Tailwind con Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { font-family: 'Outfit', sans-serif; }
        
        /* 1. Cuadrículas de diseño marcadas en rojo */
        .bg-grid {
            background-size: 50px 50px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
        }
        
        /* 2. Efectos de desvanecimiento (Fade) para que la cuadrícula desaparezca gradualmente */
        .mask-top-right {
            -webkit-mask-image: linear-gradient(to bottom left, black 20%, transparent 80%);
            mask-image: linear-gradient(to bottom left, black 20%, transparent 80%);
        }
        .mask-bottom-left {
            -webkit-mask-image: linear-gradient(to top right, black 20%, transparent 80%);
            mask-image: linear-gradient(to top right, black 20%, transparent 80%);
        }
    </style>
</head>
<body class="bg-[#1a222c] text-gray-100 antialiased min-h-screen flex overflow-hidden">

    <!-- LADO IZQUIERDO: Formulario de Login -->
    <!-- Mantiene la columna al 50% de la pantalla y centra el contenido internamente -->
    <div class="flex w-full lg:w-1/2 flex-col justify-center items-center bg-[#1c2434] p-8 relative z-10 shadow-[5px_0_25px_-5px_rgba(0,0,0,0.3)]">
        
        <!-- Límites de ancho interno (Líneas rojas verticales que dibujaste) -->
        <div class="w-full max-w-[420px]">
            
            <a href="#" class="inline-flex items-center text-sm text-gray-400 hover:text-white mb-10 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Volver al panel de control
            </a>

            <h1 class="text-[32px] font-bold text-white mb-2 leading-tight">Iniciar sesión</h1>
            <p class="text-sm text-gray-400 mb-8">¡Introduce tu correo electrónico y contraseña para iniciar sesión!</p>

            <!-- Botones Sociales -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <button type="button" class="flex items-center justify-center gap-2 py-3 px-4 rounded border border-gray-700 bg-[#24303f] hover:bg-gray-700/50 text-sm font-medium text-white transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.6l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.4l3.7 2.9C6.2 7.1 8.9 5 12 5z"/><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.8z"/><path fill="#FBBC05" d="M5.3 14.7c-.2-.7-.4-1.5-.4-2.7s.2-2 .4-2.7L1.6 6.4C.6 8.4 0 10.6 0 13s.6 4.6 1.6 6.6l3.7-2.9z"/><path fill="#34A853" d="M12 23c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.1 0-5.8-2.1-6.7-5.3L1.6 15.9C3.5 19.7 7.4 23 12 23z"/></svg>
                    Iniciar sesión con Google
                </button>
                <button type="button" class="flex items-center justify-center gap-2 py-3 px-4 rounded border border-gray-700 bg-[#24303f] hover:bg-gray-700/50 text-sm font-medium text-white transition">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    Iniciar sesión con X
                </button>
            </div>

            <!-- Separador -->
            <div class="relative flex py-4 items-center mb-6">
                <div class="flex-grow border-t border-gray-700"></div>
                <span class="flex-shrink mx-4 text-gray-500 text-xs">O</span>
                <div class="flex-grow border-t border-gray-700"></div>
            </div>

            <!-- Manejo de Errores -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-500/10 border border-red-500/50 text-red-400 rounded text-sm">
                    @foreach ($errors->all() as $error)
                        <p>• {{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <!-- Formulario Oficial -->
            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-white mb-2">Usuario <span class="text-red-500">*</span></label>
                    <input type="text" name="usu_usuario" value="{{ old('usu_usuario') }}" placeholder="Ingresa tu usuario (ej. admin)" class="w-full rounded border border-gray-700 bg-[#1c2434] py-3.5 px-5 text-white placeholder-gray-500 text-sm focus:border-blue-500 outline-none transition" required autofocus>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white mb-2">Contraseña <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" name="password" placeholder="••••••••••••" class="w-full rounded border border-gray-700 bg-[#1c2434] py-3.5 px-5 text-white placeholder-gray-500 text-sm focus:border-blue-500 outline-none transition" required>
                        <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm py-2">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-700 bg-[#1c2434] text-blue-600 focus:ring-blue-500 cursor-pointer">
                        <span class="text-gray-400 group-hover:text-gray-300">Mantenme conectado</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-blue-500 hover:underline">¿Has olvidado tu contraseña?</a>
                    @endif
                </div>

                <button type="submit" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded transition">
                    Iniciar sesión
                </button>
            </form>

            <div class="mt-8 text-sm text-gray-400">
                ¿No tienes cuenta? <a href="{{ route('register') }}" class="text-blue-500 hover:underline">Regístrate</a>
            </div>
        </div>
    </div>

    <!-- LADO DERECHO: Panel Decorativo TailAdmin (Con cuadrículas y botón) -->
    <div class="hidden lg:flex lg:w-1/2 flex-col items-center justify-center bg-[#1a222c] relative">
        
        <!-- Detalle CSS 1: Cuadrícula Superior Derecha -->
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-grid mask-top-right"></div>
        
        <!-- Detalle CSS 2: Cuadrícula Inferior Izquierda -->
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-grid mask-bottom-left"></div>

        <!-- Contenido Central -->
        <div class="relative z-10 flex flex-col items-center">
            <div class="bg-blue-600 p-4 rounded-xl text-white font-bold text-3xl mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <h2 class="text-[32px] font-bold text-white mb-2">TailAdmin</h2>
            <p class="text-gray-400 max-w-xs text-sm text-center leading-relaxed">Plantilla de panel de administración CSS Tailwind gratuita y de código abierto</p>
        </div>

        <!-- Detalle CSS 3: Botón Flotante Modo Oscuro (Abajo a la derecha) -->
            <button @click="darkMode = !darkMode" class="absolute bottom-10 right-10 flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-white transition">
                <span x-show="!darkMode">🌙</span>
                <span x-show="darkMode" style="display: none;">☀️</span>
            </button>
    </div>

</body>
</html>