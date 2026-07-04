<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contacts\Http\Requests\CustomerRequest;
use Modules\Contacts\Http\Resources\CustomerResource;
use Modules\Contacts\Services\CustomerService;

class CustomerController extends ApiController
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(CustomerResource::collection($this->service->all(['is_active' => 1])));
        }

        return $this->ok(CustomerResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        return $this->created(new CustomerResource($this->service->create($request->validated())), 'Cliente creado.');
    }

    public function show(int $customer): JsonResponse
    {
        return $this->ok(new CustomerResource($this->service->find($customer)));
    }

    public function update(CustomerRequest $request, int $customer): JsonResponse
    {
        return $this->ok(new CustomerResource($this->service->update($customer, $request->validated())), 'Cliente actualizado.');
    }

    public function destroy(int $customer): JsonResponse
    {
        $this->service->delete($customer);

        return $this->noContent('Cliente eliminado.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        return $this->ok(['deleted' => $this->service->bulkDelete($ids)], 'Clientes eliminados.');
    }
}
