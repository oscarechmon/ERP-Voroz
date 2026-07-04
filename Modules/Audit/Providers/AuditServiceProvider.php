<?php

declare(strict_types=1);

namespace Modules\Audit\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Auditoría (visor del registro de acciones). */
class AuditServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Audit';
}
