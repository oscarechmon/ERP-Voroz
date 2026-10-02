<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Pedidos online (seguimiento de los pedidos de la tienda web). */
class OnlineOrdersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'OnlineOrders';
}
