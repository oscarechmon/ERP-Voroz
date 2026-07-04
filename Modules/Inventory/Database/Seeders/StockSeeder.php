<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Services\StockService;
use Modules\Settings\Models\Warehouse;

/**
 * Carga stock inicial de cada producto en el almacén por defecto, generando el
 * kardex correspondiente. Deja a propósito algunos productos sin stock y otros
 * en stock bajo para poblar las alertas del dashboard.
 */
class StockSeeder extends Seeder
{
    public function run(): void
    {
        if (Stock::count() > 0) {
            return;
        }

        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();
        if (! $warehouse) {
            return;
        }

        $stock = app(StockService::class);

        Product::query()->each(function (Product $product) use ($stock, $warehouse): void {
            $roll = random_int(1, 10);

            // 10% sin stock, 20% stock bajo, 70% stock normal.
            $qty = match (true) {
                $roll === 1 => 0,
                $roll <= 3 => max(1, (int) $product->stock_min - random_int(0, (int) $product->stock_min)),
                default => random_int((int) $product->stock_min + 5, (int) ($product->stock_max ?? 100)),
            };

            if ($qty > 0) {
                $stock->entry($product->id, $warehouse->id, $qty, (float) $product->cost, null, 'Stock inicial');
            }
        });
    }
}
