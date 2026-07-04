<?php

declare(strict_types=1);

namespace Modules\Catalog\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Repositories\Contracts\BrandRepositoryInterface;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    protected array $searchable = ['name'];

    protected array $filterable = ['is_active'];

    protected array $sortable = ['id', 'name', 'created_at'];

    protected function model(): Model
    {
        return new Brand();
    }
}
