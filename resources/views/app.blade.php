<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Carpeta desde la que se sirve la app ('' en la raíz del dominio): la usan el router y la API. --}}
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">
    <title>{{ config('app.name', 'Sistema') }} · ERP</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="h-full antialiased">
    {{-- Punto de montaje único de la SPA. Toda la UI se renderiza con Vue. --}}
    <div id="app"></div>
</body>
</html>
