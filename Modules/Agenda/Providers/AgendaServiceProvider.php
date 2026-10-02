<?php

declare(strict_types=1);

namespace Modules\Agenda\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo Agenda (citas de los clientes con los especialistas). */
class AgendaServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Agenda';
}
