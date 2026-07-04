<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Requests\BrandRequest;
use Modules\Catalog\Http\Resources\BrandResource;
use Modules\Catalog\Services\BrandService;

class BrandController extends ApiController
{
    public function __construct(private readonly BrandService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(BrandResource::collection($this->service->all(['is_active' => 1])));
        }

        return $this->ok(BrandResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(BrandRequest $request): JsonResponse
    {
        return $this->created(new BrandResource($this->service->create($request->validated())), 'Marca creada.');
    }

    public function show(int $brand): JsonResponse
    {
        return $this->ok(new BrandResource($this->service->find($brand)));
    }

    public function update(BrandRequest $request, int $brand): JsonResponse
    {
        return $this->ok(new BrandResource($this->service->update($brand, $request->validated())), 'Marca actualizada.');
    }

    public function destroy(int $brand): JsonResponse
    {
        $this->service->delete($brand);

        return $this->noContent('Marca eliminada.');
    }
}
