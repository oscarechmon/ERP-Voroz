<?php

declare(strict_types=1);

namespace Modules\Integration\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Modules\Catalog\Models\Product;
use Modules\Integration\Services\CatalogNotifier;
use Modules\Inventory\Models\Stock;

/**
 * Proveedor del módulo Integración: la API que usa la web y el aviso de cambios
 * del catálogo hacia ella.
 */
class IntegrationServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'Integration';

    public function register(): void
    {
        parent::register();

        // Una sola instancia por petición: junta todos los cambios en un envío.
        $this->app->singleton(CatalogNotifier::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Cualquier cambio de un producto o de su stock (POS, compras, ajustes,
        // la propia web) se avisa a la web, venga del módulo que venga.
        $notify = fn (int $productId) => $this->app->make(CatalogNotifier::class)->touch($productId);

        Product::saved(fn (Product $product) => $notify($product->id));
        Product::deleted(fn (Product $product) => $notify($product->id));
        Product::restored(fn (Product $product) => $notify($product->id));
        Stock::saved(fn (Stock $stock) => $notify((int) $stock->product_id));
    }
}
