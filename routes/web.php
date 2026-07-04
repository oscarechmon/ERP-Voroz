<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mantenimiento vía web (para hosting sin SSH cómodo)
|--------------------------------------------------------------------------
| Ejecuta los comandos de despliegue (clear de cachés y, opcionalmente,
| migrate/seed/cache) sin necesidad de terminal. Protegida con un token
| derivado del APP_KEY (secreto): si el token no coincide, responde 404 y
| no revela su existencia.
|
| Uso:  /deploy/{token}
|   ?migrate=1  → php artisan migrate --force
|   ?seed=1     → php artisan db:seed --force
|   ?storage=1  → php artisan storage:link
|   ?cache=1    → re-cachea config y rutas (producción)
*/
Route::get('deploy/{token}', function (string $token) {
    abort_unless(hash_equals(hash('sha256', (string) config('app.key')), $token), 404);

    $output = [];
    $run = function (string $name, array $params = []) use (&$output): void {
        Artisan::call($name, $params);
        $output[$name] = trim(Artisan::output());
    };

    $run('config:clear');
    $run('route:clear');
    $run('view:clear');
    $run('cache:clear');

    if (request()->boolean('migrate')) {
        $run('migrate', ['--force' => true]);
    }
    if (request()->boolean('seed')) {
        $run('db:seed', ['--force' => true]);
    }
    if (request()->boolean('storage')) {
        $run('storage:link');
    }
    if (request()->boolean('cache')) {
        $run('config:cache');
        $run('route:cache');
    }

    return response()->json(['ok' => true, 'steps' => $output]);
})->name('deploy');

/*
|--------------------------------------------------------------------------
| Rutas Web
|--------------------------------------------------------------------------
| La aplicación es una SPA. Blade sólo entrega el punto de entrada; el
| enrutamiento real lo maneja Vue Router en el cliente. Esta ruta "catch-all"
| devuelve la misma vista para cualquier URL que no sea de la API o de assets,
| permitiendo recargar el navegador en cualquier ruta del SPA.
*/
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|sanctum|storage|build).*$')
    ->name('spa');
