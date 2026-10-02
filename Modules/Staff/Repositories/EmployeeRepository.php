<?php

declare(strict_types=1);

namespace Modules\Staff\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Staff\Models\Employee;

class EmployeeRepository extends BaseRepository
{
    protected array $searchable = ['name', 'position', 'phone', 'doc_number'];

    protected array $filterable = ['is_active', 'user_id'];

    protected array $sortable = ['id', 'name', 'position', 'created_at'];

    protected array $defaultWith = ['services:id,name', 'user:id,name,email'];

    protected function model(): Model
    {
        return new Employee;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['service_id'])) {
            $query->forService((int) $filters['service_id']);
        }

        return parent::applyFilters($query, $filters);
    }
}
