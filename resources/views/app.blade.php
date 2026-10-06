<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Carpeta desde la que se sirve la app ('' en la raíz del dominio): la usan el router y la API. --}}
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">
    <title>{{ config('app.name', 'Sistema') }} · ERP</title>
    <script>
        // Tema antes de pintar: sin esto, en modo oscuro se ve un destello blanco al cargar.
        try {
            var ui = JSON.parse(localStorage.getItem('sistema.ui') || '{}');
            var dark = typeof ui.dark === 'boolean' ? ui.dark : window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (dark) document.documentElement.classList.add('app-dark');
        } catch (e) {}
        // Quién está conectado (lo mismo que /auth/me): la app arranca sin esperar esa respuesta.
        window.__SISTEMA_SESION__ = @json($bootUser ?? null);
    </script>
    <style>
        /* Mientras llega la app: un indicador en vez de la pantalla en blanco. */
        .boot { display: grid; place-items: center; height: 100vh; }
        .boot__spinner { width: 34px; height: 34px; border: 3px solid rgba(51, 102, 255, .2); border-top-color: #3366ff; border-radius: 50%; animation: boot-spin .8s linear infinite; }
        @keyframes boot-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .boot__spinner { animation-duration: 2.4s; } }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="h-full antialiased">
    {{-- Punto de montaje único de la SPA. Toda la UI se renderiza con Vue; al montar reemplaza el indicador. --}}
    <div id="app"><div class="boot" role="status" aria-label="Cargando"><span class="boot__spinner"></span></div></div>
</body>
</html>
