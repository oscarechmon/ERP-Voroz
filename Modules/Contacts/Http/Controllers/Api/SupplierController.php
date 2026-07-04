<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contacts\Http\Requests\SupplierRequest;
use Modules\Contacts\Http\Resources\SupplierResource;
use Modules\Contacts\Services\SupplierService;

class SupplierController extends ApiController
{
    public function __construct(private readonly SupplierService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(SupplierResource::collection($this->service->all(['is_active' => 1])));
        }

        return $this->ok(SupplierResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return $this->created(new SupplierResource($this->service->create($request->validated())), 'Proveedor creado.');
    }

    public function show(int $supplier): JsonResponse
    {
        return $this->ok(new SupplierResource($this->service->find($supplier)));
    }

    public function update(SupplierRequest $request, int $supplier): JsonResponse
    {
        return $this->ok(new SupplierResource($this->service->update($supplier, $request->validated())), 'Proveedor actualizado.');
    }

    public function destroy(int $supplier): JsonResponse
    {
        $this->service->delete($supplier);

        return $this->noContent('Proveedor eliminado.');
    }
}
