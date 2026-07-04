<?php

declare(strict_types=1);

namespace Modules\Purchases\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Modules\Purchases\Models\Purchase;

class PurchaseRepository extends BaseRepository
{
    protected array $searchable = ['number', 'supplier_doc', 'notes'];

    protected array $filterable = ['supplier_id', 'status', 'doc_type'];

    protected array $sortable = ['id', 'number', 'total', 'purchased_at', 'created_at'];

    protected array $defaultWith = ['supplier:id,name,doc_number', 'user:id,name'];

    protected function model(): Model
    {
        return new Purchase();
    }
}
