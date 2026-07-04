<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Inventario (stock, kardex, ajustes, transferencias). */
class InventoryServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Inventory';
}
