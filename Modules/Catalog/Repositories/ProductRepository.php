<?php

declare(strict_types=1);

namespace Modules\Catalog\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected array $searchable = ['name', 'code', 'barcode', 'sku'];

    protected array $filterable = ['category_id', 'brand_id', 'unit_id', 'is_active'];

    protected array $sortable = ['id', 'name', 'code', 'price', 'cost', 'created_at'];

    protected array $defaultWith = ['category:id,name', 'brand:id,name', 'unit:id,name,abbreviation'];

    protected function model(): Model
    {
        return new Product();
    }

    /** Añade el stock total sumado (withSum) para evitar N+1 en los listados. */
    public function query(): Builder
    {
        return parent::query()->withSum('stocks', 'quantity');
    }

    public function findByBarcode(string $barcode): ?Model
    {
        return $this->query()
            ->where('barcode', $barcode)
            ->orWhereHas('barcodes', fn ($q) => $q->where('barcode', $barcode))
            ->first();
    }

    public function nextCode(): string
    {
        // Toma el mayor correlativo existente con prefijo PRD- y suma 1.
        $last = Product::withTrashed()
            ->where('code', 'like', 'PRD-%')
            ->orderByDesc('id')
            ->value('code');

        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'PRD-' . str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
