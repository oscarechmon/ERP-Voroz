<?php

declare(strict_types=1);

namespace Modules\Users\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo de Usuarios (perfil, estado, roles asignados). */
class UsersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Users';
}
