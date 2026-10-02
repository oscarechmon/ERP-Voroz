<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\Stock;

/**
 * Lo que la web sabe de cada ítem del catálogo: lo que el sistema administra
 * (nombre, categoría, precio, stock, estado). Imágenes, descripciones y qué se
 * publica los decide la web.
 *
 * Incluye los eliminados (como inactivos) para que la web también los oculte.
 */
class CatalogFeed
{
    public function __construct(private readonly IntegrationWarehouse $warehouse) {}

    /**
     * @param  list<int>|null  $ids  Solo esos productos; null = todo el catálogo.
     * @return list<array<string, mixed>>
     */
    public function items(?array $ids = null): array
    {
        return Product::withTrashed()
            ->with(['category:id,name', 'unit:id,name'])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->addSelect(['web_stock' => Stock::select('quantity')
                ->whereColumn('stocks.product_id', 'products.id')
                ->where('warehouse_id', $this->warehouse->id())
                ->limit(1)])
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'type' => $product->type,
                'code' => $product->code,
                'name' => $product->name,
                'description' => $product->description,
                'category' => $product->category?->name,
                'unit' => $product->unit?->name,
                'price' => (float) $product->price,
                'cost' => (float) $product->cost,
                'track_stock' => $product->track_stock,
                'stock' => $product->track_stock ? (float) ($product->web_stock ?? 0) : null,
                'stock_min' => (float) $product->stock_min,
                'active' => $product->is_active && ! $product->trashed(),
            ])
            ->all();
    }

    /**
     * Stock que ve la web de unos productos. Lo devuelven las operaciones que
     * lo mueven, para que la web actualice su copia sin esperar el aviso.
     *
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    public function stock(array $productIds): array
    {
        $tracked = Product::withTrashed()->whereIn('id', $productIds)->where('track_stock', true)->pluck('id');

        $quantities = Stock::where('warehouse_id', $this->warehouse->id())
            ->whereIn('product_id', $tracked)
            ->pluck('quantity', 'product_id');

        return $tracked->mapWithKeys(fn (int $id): array => [$id => (float) ($quantities[$id] ?? 0)])->all();
    }
}
