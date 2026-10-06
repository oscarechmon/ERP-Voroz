<?php

use App\Http\Controllers\ReleaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Resources\UserResource;

/*
|--------------------------------------------------------------------------
| Publicación desde GitHub Actions (ver DEPLOY.md)
|--------------------------------------------------------------------------
| Descomprime el release.zip subido por FTP, migra y cachea. Se valida con
| el token de DEPLOY_TOKEN, no con sesión ni CSRF. Va por tandas, de ahí el
| límite alto.
*/
Route::post('deploy/release', ReleaseController::class)->middleware('throttle:120,10')->name('deploy.release');

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

    // Diagnóstico: muestra los valores realmente cargados (sin secretos) para
    // depurar problemas de sesión/Sanctum en producción.  → /deploy/{token}?info=1
    if (request()->boolean('info')) {
        return response()->json([
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'app_debug' => config('app.debug'),
            'request_secure' => request()->secure(),
            'request_host' => request()->getHost(),
            'session_driver' => config('session.driver'),
            'session_domain' => config('session.domain'),
            'session_secure_cookie' => config('session.secure'),
            'session_same_site' => config('session.same_site'),
            'sanctum_stateful' => config('sanctum.stateful'),
            'users_count' => \App\Models\User::count(),
            'admin_exists' => \App\Models\User::where('email', 'admin@sistema.test')->exists(),
        ]);
    }

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
    // Refresca sólo permisos/roles (idempotente, no toca datos): /deploy/{token}?perms=1
    if (request()->boolean('perms')) {
        $run('db:seed', [
            '--force' => true,
            '--class' => \Modules\Users\Database\Seeders\RolePermissionSeeder::class,
        ]);
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
Route::get('/{any?}', function () {
    // La sesión viaja dentro de la página: la SPA arranca sin esperar una
    // segunda petición a /auth/me. Por eso la página no se guarda en ninguna
    // caché (lleva los datos de quien la pidió).
    $user = auth()->user();

    return response()
        ->view('app', ['bootUser' => $user ? (new UserResource($user->load(['roles', 'permissions'])))->resolve() : null])
        ->header('Cache-Control', 'no-store, private');
})
    ->where('any', '^(?!api|sanctum|storage|build).*$')
    ->name('spa');
