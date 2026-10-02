<?php

declare(strict_types=1);

namespace Modules\Staff\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Personal (especialistas y recepción, y los servicios que atiende cada uno). */
class StaffServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Staff';
}
