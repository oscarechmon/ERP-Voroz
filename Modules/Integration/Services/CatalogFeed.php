<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Packages\Models\Package;

/**
 * Todo lo que la web muestra de cada ítem del catálogo: nombre, categoría,
 * precio, stock y estado, y también lo de la web pública (si se publica,
 * imagen, descripción y, en un servicio, su duración). La web no guarda nada
 * de esto: lo lee de aquí.
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
        $packages = Package::withTrashed()
            ->with('services:id')
            ->when($ids !== null, fn ($q) => $q->whereIn('product_id', $ids))
            ->whereNotNull('product_id')
            ->get()
            ->keyBy('product_id');

        return Product::withTrashed()
            ->with(['category:id,name,description', 'unit:id,name'])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->addSelect(['web_stock' => Stock::select('quantity')
                ->whereColumn('stocks.product_id', 'products.id')
                ->where('warehouse_id', $this->warehouse->id())
                ->limit(1)])
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => $this->item($product, $packages->get($product->id)))
            ->all();
    }

    /** @return array<string, mixed> */
    private function item(Product $product, ?Package $package): array
    {
        return [
            'id' => $product->id,
            'type' => $product->type,
            'code' => $product->code,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category?->name,
            'category_description' => $product->category?->description,
            'unit' => $product->unit?->name,
            'price' => (float) $product->price,
            'cost' => (float) $product->cost,
            'track_stock' => $product->track_stock,
            'stock' => $product->track_stock ? (float) ($product->web_stock ?? 0) : null,
            'stock_min' => (float) $product->stock_min,
            'active' => $product->is_active && ! $product->trashed(),
            // Vacío: aún no se decidió aquí (la web sigue con lo que tenía).
            'web_published' => $product->web_published,
            // Dirección completa: la web la muestra tal cual, desde aquí.
            'image_url' => $product->image_url ? url($product->image_url) : null,
            'duration_minutes' => $product->duration_minutes,
            // Un paquete lleva sus sesiones, vigencia y servicios (ids de aquí).
            'package' => $product->isPackage() && $package ? [
                'total_sessions' => $package->total_sessions,
                'validity_days' => $package->validity_days,
                'service_ids' => $package->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ] : null,
        ];
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
