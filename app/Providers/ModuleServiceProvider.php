<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Proveedor raíz del sistema modular.
 *
 * Auto-descubre cada `Modules/<Modulo>/Providers/<Modulo>ServiceProvider.php` y lo
 * registra. Así basta con crear un módulo nuevo con su provider para que el sistema
 * lo cargue, sin tocar la configuración central (extensible / preparado para módulos
 * futuros como CRM o Facturación SUNAT).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->discoverModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Devuelve la lista de clases ServiceProvider de todos los módulos presentes.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function discoverModuleProviders(): array
    {
        // Normaliza a barras `/` para que el patrón y las rutas devueltas por glob
        // coincidan tanto en Windows como en Linux (glob devuelve barras `/`).
        $modulesPath = str_replace('\\', '/', base_path('Modules'));

        if (! is_dir($modulesPath)) {
            return [];
        }

        $providers = [];

        foreach (glob($modulesPath . '/*/Providers/*ServiceProvider.php') ?: [] as $file) {
            // .../Modules/Catalog/Providers/CatalogServiceProvider.php
            //   -> Modules\Catalog\Providers\CatalogServiceProvider
            $file = str_replace('\\', '/', $file);
            $relative = trim(str_replace($modulesPath, '', $file), '/');
            $relative = str_replace(['/', '.php'], ['\\', ''], $relative);
            $class = 'Modules\\' . $relative;

            if (class_exists($class)) {
                $providers[] = $class;
            }
        }

        sort($providers);

        return $providers;
    }
}
