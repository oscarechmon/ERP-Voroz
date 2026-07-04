<?php

declare(strict_types=1);

namespace Modules\Users\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository
{
    protected array $searchable = ['name', 'email', 'phone'];

    protected array $filterable = ['is_active', 'company_id'];

    protected array $sortable = ['id', 'name', 'email', 'created_at', 'last_login_at'];

    protected array $defaultWith = ['roles:id,name'];

    protected function model(): Model
    {
        return new User();
    }
}
