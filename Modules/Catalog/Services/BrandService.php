<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Core\Services\BaseService;
use Modules\Catalog\Repositories\Contracts\BrandRepositoryInterface;

class BrandService extends BaseService
{
    public function __construct(BrandRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
