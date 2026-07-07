<?php

declare(strict_types=1);

namespace Modules\Sales\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Sales\Models\Sale;

class SaleRepository extends BaseRepository
{
    protected array $searchable = ['full_number', 'notes'];

    protected array $filterable = ['doc_type', 'status', 'customer_id', 'user_id', 'payment_status'];

    protected array $sortable = ['id', 'full_number', 'total', 'sold_at', 'created_at'];

    protected array $defaultWith = ['customer:id,name,doc_number', 'user:id,name'];

    protected function model(): Model
    {
        return new Sale;
    }
}
