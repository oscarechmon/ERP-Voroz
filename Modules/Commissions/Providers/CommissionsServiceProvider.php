<?php

declare(strict_types=1);

namespace Modules\Commissions\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Comisiones (reglas y comisiones del personal). */
class CommissionsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Commissions';
}
