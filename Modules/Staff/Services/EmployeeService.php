<?php

declare(strict_types=1);

namespace Modules\Staff\Services;

use App\Core\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Staff\Models\Employee;
use Modules\Staff\Repositories\EmployeeRepository;

/**
 * Alta y edición del personal con los servicios que atiende. Eliminar es un
 * borrado lógico: sus citas, atenciones y comisiones lo siguen mostrando.
 */
class EmployeeService extends BaseService
{
    public function __construct(EmployeeRepository $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data): Employee {
            /** @var Employee $employee */
            $employee = parent::create($this->attributes($data));
            $employee->services()->sync($data['service_ids'] ?? []);

            return $employee->load('services:id,name', 'user:id,name,email');
        });
    }

    public function update(int|string $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data): Employee {
            /** @var Employee $employee */
            $employee = parent::update($id, $this->attributes($data));
            if (array_key_exists('service_ids', $data)) {
                $employee->services()->sync($data['service_ids'] ?? []);
            }

            return $employee->load('services:id,name', 'user:id,name,email');
        });
    }

    /** @return array<string, mixed> */
    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip((new Employee)->getFillable()));
    }
}
