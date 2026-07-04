<?php

declare(strict_types=1);

namespace Modules\Settings\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo de Configuración (empresa, sucursales, almacenes, series). */
class SettingsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Settings';
}
