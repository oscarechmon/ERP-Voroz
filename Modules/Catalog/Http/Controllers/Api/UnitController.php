<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Requests\UnitRequest;
use Modules\Catalog\Http\Resources\UnitResource;
use Modules\Catalog\Services\UnitService;

class UnitController extends ApiController
{
    public function __construct(private readonly UnitService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(UnitResource::collection($this->service->all(['is_active' => 1])));
        }

        return $this->ok(UnitResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(UnitRequest $request): JsonResponse
    {
        return $this->created(new UnitResource($this->service->create($request->validated())), 'Unidad creada.');
    }

    public function show(int $unit): JsonResponse
    {
        return $this->ok(new UnitResource($this->service->find($unit)));
    }

    public function update(UnitRequest $request, int $unit): JsonResponse
    {
        return $this->ok(new UnitResource($this->service->update($unit, $request->validated())), 'Unidad actualizada.');
    }

    public function destroy(int $unit): JsonResponse
    {
        $this->service->delete($unit);

        return $this->noContent('Unidad eliminada.');
    }
}
