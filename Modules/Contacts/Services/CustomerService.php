<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Core\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\Contacts\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerService extends BaseService
{
    public function __construct(CustomerRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): Model
    {
        $data['company_id'] = $data['company_id'] ?? optional(auth()->user())->company_id;

        return parent::create($data);
    }
}
