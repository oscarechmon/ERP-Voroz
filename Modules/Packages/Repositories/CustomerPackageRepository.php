<?php

declare(strict_types=1);

namespace Modules\Packages\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Packages\Models\CustomerPackage;

class CustomerPackageRepository extends BaseRepository
{
    protected array $searchable = ['package_name'];

    protected array $filterable = ['customer_id', 'package_id', 'status', 'sale_id'];

    protected array $sortable = ['id', 'purchased_at', 'expires_at', 'created_at'];

    protected array $defaultWith = ['customer:id,code,name,doc_number', 'package:id,name'];

    protected function model(): Model
    {
        return new CustomerPackage;
    }

    /**
     * Extras: `usable=1` solo los que tienen sesiones hoy; `service_id` los que
     * incluyen ese servicio; la búsqueda también mira el nombre del cliente.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['usable'])) {
            $query->usable();
        }

        if (! empty($filters['service_id'])) {
            $serviceId = (int) $filters['service_id'];
            $query->whereHas('package.services', fn (Builder $q) => $q->where('products.id', $serviceId));
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(fn (Builder $q) => $q
                ->where('package_name', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")));
            unset($filters['search']);
        }

        return parent::applyFilters($query, $filters);
    }
}
