<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Models\Customer;
use Modules\Contacts\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected array $searchable = ['name', 'doc_number', 'email', 'phone'];

    protected array $filterable = ['doc_type', 'is_active'];

    protected array $sortable = ['id', 'name', 'doc_number', 'created_at'];

    protected function model(): Model
    {
        return new Customer();
    }
}
