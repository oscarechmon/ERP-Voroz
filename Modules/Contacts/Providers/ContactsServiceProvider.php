<?php

declare(strict_types=1);

namespace Modules\Contacts\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Modules\Contacts\Repositories\Contracts\CustomerRepositoryInterface;
use Modules\Contacts\Repositories\Contracts\SupplierRepositoryInterface;
use Modules\Contacts\Repositories\CustomerRepository;
use Modules\Contacts\Repositories\SupplierRepository;

/** Proveedor del módulo Contactos (Clientes y Proveedores). */
class ContactsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Contacts';

    protected function bindings(): array
    {
        return [
            CustomerRepositoryInterface::class => CustomerRepository::class,
            SupplierRepositoryInterface::class => SupplierRepository::class,
        ];
    }
}
