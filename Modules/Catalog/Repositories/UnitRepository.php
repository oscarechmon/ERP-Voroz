<?php

declare(strict_types=1);

namespace Modules\Catalog\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Unit;
use Modules\Catalog\Repositories\Contracts\UnitRepositoryInterface;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    protected array $searchable = ['name', 'abbreviation'];

    protected array $filterable = ['is_active'];

    protected array $sortable = ['id', 'name', 'created_at'];

    protected function model(): Model
    {
        return new Unit();
    }
}
