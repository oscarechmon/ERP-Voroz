<?php

declare(strict_types=1);

namespace Modules\Packages\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Packages\Models\Package;

class PackageRepository extends BaseRepository
{
    protected array $searchable = ['name', 'description'];

    protected array $filterable = ['is_active'];

    protected array $sortable = ['id', 'name', 'price', 'total_sessions', 'created_at'];

    protected array $defaultWith = ['services:id,name', 'product:id,code'];

    protected function model(): Model
    {
        return new Package;
    }
}
