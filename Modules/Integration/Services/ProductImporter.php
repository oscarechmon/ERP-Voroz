<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Unit;
use Modules\Catalog\Services\ProductService;
use Modules\Inventory\Services\StockService;
use Modules\Settings\Models\Company;

/**
 * Alta en el sistema de lo que la web ya tenía (productos, insumos y servicios),
 * una sola vez, al conectar las dos aplicaciones.
 *
 * El código que manda la web (WEB-P-12, WEB-S-5…) hace de llave: importar dos
 * veces el mismo ítem devuelve el producto que ya se creó, así un reintento
 * nunca lo duplica.
 */
class ProductImporter
{
    public function __construct(
        private readonly ProductService $products,
        private readonly StockService $stock,
        private readonly IntegrationWarehouse $warehouse,
    ) {}

    /** @param  array<string, mixed>  $data  Validado por ImportProductRequest. */
    public function import(array $data): Product
    {
        $existing = Product::withTrashed()->where('code', $data['code'])->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($data): Product {
            /** @var Product $product */
            $product = $this->products->create([
                'company_id' => Company::query()->value('id'),
                'type' => $data['type'],
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $this->categoryId($data['category'] ?? null),
                'unit_id' => $this->unitId($data['unit'] ?? null),
                'price' => $data['price'] ?? 0,
                'cost' => $data['cost'] ?? 0,
                'stock_min' => $data['stock_min'] ?? 0,
                'track_stock' => $data['type'] === Product::TYPE_PRODUCT,
                'is_active' => $data['active'] ?? true,
            ]);

            // El stock que tenía la web entra como saldo inicial, con su kardex.
            $initial = (float) ($data['stock'] ?? 0);
            if ($product->track_stock && $initial > 0) {
                $this->stock->entry(
                    $product->id,
                    $this->warehouse->id(),
                    $initial,
                    (float) $product->cost,
                    null,
                    'Stock inicial importado de la web',
                );
            }

            return $product;
        });
    }

    private function categoryId(?string $name): ?int
    {
        $name = trim((string) $name);

        return $name === '' ? null : Category::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    /** La web guarda la unidad como texto libre; solo se enlaza si ya existe. */
    private function unitId(?string $name): ?int
    {
        $name = trim((string) $name);

        return $name === '' ? null : Unit::where('name', $name)->orWhere('abbreviation', $name)->value('id');
    }
}
