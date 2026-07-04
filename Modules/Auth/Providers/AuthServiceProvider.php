<?php

declare(strict_types=1);

namespace Modules\Auth\Providers;

use App\Core\Providers\ModuleServiceProvider;

/** Proveedor del módulo de Autenticación (login/logout/me, sesión SPA Sanctum). */
class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Auth';
}
