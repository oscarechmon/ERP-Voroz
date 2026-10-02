<?php

declare(strict_types=1);

namespace Modules\Attendances\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Atenciones (servicios realizados, insumos, sesiones y comisiones). */
class AttendancesServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Attendances';
}
