<?php

declare(strict_types=1);

namespace Modules\Packages\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Packages\Listeners\CancelCustomerPackagesOfSale;
use Modules\Packages\Listeners\CreateCustomerPackagesFromSale;
use Modules\Sales\Events\SaleCancelled;
use Modules\Sales\Events\SaleCompleted;

/** Proveedor del módulo Paquetes (catálogo de paquetes y saldo de sesiones de cada cliente). */
class PackagesServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Packages';

    public function boot(): void
    {
        parent::boot();

        Event::listen(SaleCompleted::class, CreateCustomerPackagesFromSale::class);
        Event::listen(SaleCancelled::class, CancelCustomerPackagesOfSale::class);
    }
}
