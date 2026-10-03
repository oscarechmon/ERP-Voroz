<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\ProductService;
use Modules\Packages\Models\Package;
use Modules\Packages\Services\PackageService;

/**
 * Trae al sistema lo que la web mostraba de cada ítem (imagen, descripción, si
 * se publicaba, duración) para que desde aquí se administre todo. Se usa una
 * vez, al pasar el catálogo de la web al sistema.
 */
class WebDetailsService
{
    public function __construct(
        private readonly ProductService $products,
        private readonly PackageService $packages,
    ) {}

    /** @param  array{description?: ?string, web_published?: ?bool, duration_minutes?: ?int}  $data */
    public function apply(Product $product, array $data, ?UploadedFile $image): Product
    {
        return DB::transaction(function () use ($product, $data, $image): Product {
            $fields = array_intersect_key($data, array_flip(['description', 'web_published', 'duration_minutes']));

            if ($product->isPackage()) {
                // El producto de un paquete se copia desde el paquete.
                $package = Package::where('product_id', $product->id)->first();
                if ($package) {
                    $package->update(array_intersect_key($fields, array_flip(['description', 'web_published'])));
                    $this->packages->syncProduct($package);
                }
            } else {
                $product->update($fields);
            }

            if ($image) {
                $this->products->replaceImage($product, $image);
                // La imagen se guarda sin eventos: así la web se entera.
                $product->touch();
            }

            return $product->fresh();
        });
    }

    /**
     * Descripción de una categoría, que la web muestra sobre sus productos o
     * servicios. Solo si aquí todavía no tiene una.
     */
    public function describeCategory(string $name, string $description): bool
    {
        $category = Category::where('name', $name)->first();

        if (! $category || filled($category->description)) {
            return false;
        }

        return $category->update(['description' => $description]);
    }
}
