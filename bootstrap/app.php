<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Habilita la autenticación SPA de Sanctum (sesión por cookie) para /api.
        $middleware->statefulApi();

        // Alias de middleware de spatie/laravel-permission para proteger rutas
        // por permiso/rol (p. ej. ->middleware('permission:products.create')).
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las excepciones de negocio se renderizan solas (método render()).
        // Aquí se pueden mapear excepciones adicionales a respuestas JSON.
    })->create();
