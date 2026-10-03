<?php

declare(strict_types=1);

namespace Modules\Integration\Providers;

use App\Core\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Integration\Console\VorozImportarCommand;
use Modules\Integration\Services\CatalogNotifier;
use Modules\Integration\Services\OrderStatusNotifier;
use Modules\Integration\Services\WebLinks;
use Modules\Inventory\Models\Stock;
use Modules\OnlineOrders\Events\OnlineOrderStatusChanged;

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
        // Los enlaces con la web se cachean durante la petición (importación por tandas).
        $this->app->scoped(WebLinks::class);
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
        // La web agrupa por categoría y muestra su descripción.
        Category::saved(fn (Category $category) => Product::where('category_id', $category->id)->pluck('id')->each(fn ($id) => $notify((int) $id)));

        // Cada paso del seguimiento de un pedido se avisa a la web (lo ve el cliente).
        Event::listen(OnlineOrderStatusChanged::class, OrderStatusNotifier::class);

        if ($this->app->runningInConsole()) {
            $this->commands([VorozImportarCommand::class]);
        }
    }
}
