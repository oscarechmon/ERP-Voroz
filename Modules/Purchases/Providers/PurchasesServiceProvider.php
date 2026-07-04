<?php

declare(strict_types=1);

namespace Modules\Purchases\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Compras. */
class PurchasesServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Purchases';
}
