<?php

declare(strict_types=1);

namespace Modules\Sales\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Ventas (POS, comprobantes, correlativos). */
class SalesServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Sales';

    public function boot(): void
    {
        parent::boot();

        // Vistas del módulo (namespace `sales::`) para los PDF de comprobantes.
        $this->loadViewsFrom($this->modulePath.'/resources/views', 'sales');
    }
}
