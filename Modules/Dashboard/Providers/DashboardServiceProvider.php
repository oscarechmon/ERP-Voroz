<?php

declare(strict_types=1);

namespace Modules\Dashboard\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Dashboard (métricas agregadas). */
class DashboardServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Dashboard';
}
