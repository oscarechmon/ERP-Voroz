<?php

declare(strict_types=1);

namespace Modules\Attendances\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendances\Models\Attendance;

class AttendanceRepository extends BaseRepository
{
    protected array $searchable = ['observations'];

    protected array $filterable = ['customer_id', 'employee_id', 'service_id', 'customer_package_id'];

    protected array $sortable = ['id', 'attended_at', 'created_at'];

    protected array $defaultWith = ['customer:id,code,name', 'service:id,name', 'employee:id,name', 'commission:id,attendance_id,amount,status'];

    protected function model(): Model
    {
        return new Attendance;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['from'])) {
            $query->whereDate('attended_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('attended_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(fn (Builder $q) => $q
                ->where('observations', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")));
            unset($filters['search']);
        }

        $filters['sort_by'] ??= 'attended_at';

        return parent::applyFilters($query, $filters);
    }
}
