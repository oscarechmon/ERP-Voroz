<?php

declare(strict_types=1);

namespace Modules\Catalog\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['parent_id', 'is_active'];

    protected array $sortable = ['id', 'name', 'sort_order', 'created_at'];

    protected array $defaultWith = ['parent:id,name'];

    protected function model(): Model
    {
        return new Category();
    }
}
