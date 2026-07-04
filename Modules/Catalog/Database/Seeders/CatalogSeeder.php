<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Unit;
use Modules\Catalog\Services\BarcodeService;
use Modules\Settings\Models\Company;

/** Siembra catálogo demo: unidades, marcas, categorías y ~60 productos con barras. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $barcodes = app(BarcodeService::class);

        $units = Unit::count() ? Unit::all() : Unit::factory(6)->create();
        $brands = Brand::count() ? Brand::all() : Brand::factory(8)->create();
        $categories = Category::count() ? Category::all() : Category::factory(8)->create();

        if (Product::count() > 0) {
            return;
        }

        Product::factory(60)
            ->make()
            ->each(function (Product $product) use ($company, $categories, $brands, $units, $barcodes): void {
                $product->company_id = $company?->id;
                $product->category_id = $categories->random()->id;
                $product->brand_id = $brands->random()->id;
                $product->unit_id = $units->random()->id;
                $product->save();

                // Genera un EAN-13 válido por producto.
                $product->barcode = $barcodes->generateEan13($product->id);
                $product->saveQuietly();
            });
    }
}
