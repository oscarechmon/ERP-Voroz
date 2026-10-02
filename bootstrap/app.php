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
        // Hosting compartido (Hostinger) sirve tras un proxy que termina el TLS.
        // Confiar en el proxy permite que Laravel detecte HTTPS (X-Forwarded-Proto)
        // y marque la cookie de sesión como Secure: imprescindible para Sanctum SPA.
        $middleware->trustProxies(at: '*');

        // Habilita la autenticación SPA de Sanctum (sesión por cookie) para /api.
        $middleware->statefulApi();

        // Alias de middleware de spatie/laravel-permission para proteger rutas
        // por permiso/rol (p. ej. ->middleware('permission:products.create')).
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // La llama GitHub Actions, que no tiene sesión: se valida con DEPLOY_TOKEN.
        $middleware->validateCsrfTokens(except: ['deploy/release']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las excepciones de negocio se renderizan solas (método render()).
        // Aquí se pueden mapear excepciones adicionales a respuestas JSON.
    })->create();
