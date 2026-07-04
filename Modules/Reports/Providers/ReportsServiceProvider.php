<?php

declare(strict_types=1);

namespace Modules\Reports\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Reportes. */
class ReportsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Reports';

    public function boot(): void
    {
        parent::boot();

        // Vistas del módulo (namespace `reports::`) para los PDF de reportes.
        $this->loadViewsFrom($this->modulePath . '/resources/views', 'reports');
    }
}
