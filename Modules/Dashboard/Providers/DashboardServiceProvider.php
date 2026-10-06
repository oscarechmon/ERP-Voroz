<?php

declare(strict_types=1);

namespace Modules\Dashboard\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Modules\Dashboard\Services\DashboardService;
use Modules\Sales\Models\Sale;

/** Proveedor del módulo Dashboard (métricas agregadas). */
class DashboardServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Dashboard';

    public function boot(): void
    {
        parent::boot();

        // Las métricas se reutilizan un minuto; una venta nueva, anulada o
        // eliminada las deja al día en la siguiente visita.
        Sale::saved(fn () => DashboardService::forget());
        Sale::deleted(fn () => DashboardService::forget());
    }
}
