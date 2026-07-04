<?php

declare(strict_types=1);

namespace App\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Clase base para el ServiceProvider de cada módulo.
 *
 * Estandariza la carga de rutas API, migraciones y policies, y expone puntos de
 * extensión (`bindings()`, `policies()`) para cablear las interfaces con sus
 * implementaciones (Inversión de Dependencias). Cada módulo sólo declara su
 * nombre y directorio; el resto es automático (DRY).
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Nombre del módulo, p. ej. "Catalog". */
    protected string $moduleName = '';

    /** Ruta absoluta del módulo. */
    protected string $modulePath = '';

    /**
     * Mapa Interface::class => Implementación::class a registrar en el contenedor.
     *
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [];
    }

    /**
     * Mapa Modelo::class => Policy::class del módulo.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /** Resuelve la ruta raíz del módulo a partir de la ubicación del provider. */
    protected function resolveModulePath(): string
    {
        if ($this->modulePath !== '') {
            return $this->modulePath;
        }

        // El provider vive en Modules/<Modulo>/Providers/, el módulo es su abuelo.
        $providerFile = (new ReflectionClass(static::class))->getFileName();

        return $this->modulePath = dirname($providerFile, 2);
    }

    public function register(): void
    {
        $this->modulePath = $this->resolveModulePath();

        foreach ($this->bindings() as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }

        $config = $this->modulePath . '/Config/config.php';
        if (is_file($config)) {
            $this->mergeConfigFrom($config, strtolower($this->moduleName));
        }
    }

    public function boot(): void
    {
        $this->modulePath = $this->resolveModulePath();
        $this->loadRoutes();
        $this->loadMigrationsFrom($this->modulePath . '/Database/Migrations');

        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /** Carga las rutas del módulo bajo el prefijo /api/v1 con middleware de API. */
    protected function loadRoutes(): void
    {
        $routes = $this->modulePath . '/routes/api.php';

        if (is_file($routes)) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group($routes);
        }
    }
}
