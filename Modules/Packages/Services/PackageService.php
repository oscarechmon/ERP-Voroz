<?php

declare(strict_types=1);

namespace Modules\Packages\Services;

use App\Core\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Packages\Models\Package;
use Modules\Packages\Repositories\PackageRepository;
use Modules\Settings\Models\Company;

/**
 * Catálogo de paquetes. Cada paquete mantiene su producto de tipo `package`
 * (código PKG-…), que es lo que el POS vende y lo que la web recibe en su
 * catálogo: nombre, precio y estado se copian ahí en cada cambio.
 */
class PackageService extends BaseService
{
    public function __construct(PackageRepository $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data): Package {
            /** @var Package $package */
            $package = parent::create($this->attributes($data));
            $package->services()->sync($data['service_ids'] ?? []);
            $this->syncProduct($package);

            return $package->fresh(['services:id,name', 'product']);
        });
    }

    public function update(int|string $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data): Package {
            /** @var Package $package */
            $package = parent::update($id, $this->attributes($data));
            if (array_key_exists('service_ids', $data)) {
                $package->services()->sync($data['service_ids'] ?? []);
            }
            $this->syncProduct($package);

            return $package->fresh(['services:id,name', 'product']);
        });
    }

    /** Borrado lógico del paquete y su producto: lo vendido conserva su copia. */
    public function delete(int|string $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            /** @var Package $package */
            $package = $this->repository->findOrFail($id);
            $package->product()->first()?->delete();

            return (bool) $package->delete();
        });
    }

    /**
     * Crea o actualiza el producto vendible del paquete. Siempre se guarda
     * (aunque no cambie nada) para que la web reciba sesiones y servicios
     * nuevos en su copia del catálogo.
     */
    public function syncProduct(Package $package): Product
    {
        $product = $package->product_id ? Product::withTrashed()->find($package->product_id) : null;

        $product ??= new Product([
            'company_id' => Company::query()->value('id'),
            'type' => Product::TYPE_PACKAGE,
            'code' => 'PKG-'.str_pad((string) $package->id, 6, '0', STR_PAD_LEFT),
            'category_id' => Category::firstOrCreate(['name' => 'Paquetes'], ['is_active' => true])->id,
            'cost' => 0,
            'track_stock' => false,
        ]);

        $product->fill([
            'name' => $package->name,
            'description' => $package->description,
            'price' => $package->price,
            'is_active' => $package->is_active,
        ]);

        if ($product->trashed()) {
            $product->restore();
        }
        $product->save();

        if ($package->product_id !== $product->id) {
            $package->forceFill(['product_id' => $product->id])->saveQuietly();
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'description', 'price', 'total_sessions', 'validity_days', 'is_active']));
    }
}
