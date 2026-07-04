<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Models\Supplier;
use Modules\Contacts\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected array $searchable = ['name', 'doc_number', 'contact_name', 'email', 'phone'];

    protected array $filterable = ['doc_type', 'is_active'];

    protected array $sortable = ['id', 'name', 'doc_number', 'created_at'];

    protected function model(): Model
    {
        return new Supplier();
    }
}
