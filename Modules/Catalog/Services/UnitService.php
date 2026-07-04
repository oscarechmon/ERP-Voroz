<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Core\Services\BaseService;
use Modules\Catalog\Repositories\Contracts\UnitRepositoryInterface;

class UnitService extends BaseService
{
    public function __construct(UnitRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
