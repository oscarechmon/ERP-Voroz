<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Policies\ProductPolicy;
use Modules\Catalog\Repositories\BrandRepository;
use Modules\Catalog\Repositories\CategoryRepository;
use Modules\Catalog\Repositories\Contracts\BrandRepositoryInterface;
use Modules\Catalog\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Catalog\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Catalog\Repositories\Contracts\UnitRepositoryInterface;
use Modules\Catalog\Repositories\ProductRepository;
use Modules\Catalog\Repositories\UnitRepository;

/** Proveedor del módulo Catálogo: cablea interfaces→implementaciones y policies. */
class CatalogServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Catalog';

    protected function bindings(): array
    {
        return [
            ProductRepositoryInterface::class => ProductRepository::class,
            CategoryRepositoryInterface::class => CategoryRepository::class,
            BrandRepositoryInterface::class => BrandRepository::class,
            UnitRepositoryInterface::class => UnitRepository::class,
        ];
    }

    protected function policies(): array
    {
        return [
            Product::class => ProductPolicy::class,
        ];
    }
}
