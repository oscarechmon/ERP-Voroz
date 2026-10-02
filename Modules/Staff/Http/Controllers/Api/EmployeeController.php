<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Staff\Http\Requests\EmployeeRequest;
use Modules\Staff\Http\Resources\EmployeeResource;
use Modules\Staff\Services\EmployeeService;

class EmployeeController extends ApiController
{
    public function __construct(private readonly EmployeeService $service) {}

    /** Listado paginado, o `all=1` para los selectores (solo activos; `service_id` filtra quién lo atiende). */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $filters = ['is_active' => 1, 'sort_by' => 'name', 'sort_dir' => 'asc'] + $request->only('service_id');

            return $this->ok(EmployeeResource::collection($this->service->all($filters)));
        }

        return $this->ok(EmployeeResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        return $this->created(new EmployeeResource($this->service->create($request->validated())), 'Empleado registrado.');
    }

    public function show(int $employee): JsonResponse
    {
        return $this->ok(new EmployeeResource($this->service->find($employee)));
    }

    public function update(EmployeeRequest $request, int $employee): JsonResponse
    {
        return $this->ok(new EmployeeResource($this->service->update($employee, $request->validated())), 'Empleado actualizado.');
    }

    public function destroy(int $employee): JsonResponse
    {
        $this->service->delete($employee);

        return $this->noContent('Empleado eliminado.');
    }
}
