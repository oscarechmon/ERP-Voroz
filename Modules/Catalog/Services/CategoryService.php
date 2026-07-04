<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Core\Services\BaseService;
use Modules\Catalog\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryService extends BaseService
{
    public function __construct(CategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
