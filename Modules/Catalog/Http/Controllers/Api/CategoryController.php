<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Requests\CategoryRequest;
use Modules\Catalog\Http\Resources\CategoryResource;
use Modules\Catalog\Services\CategoryService;

class CategoryController extends ApiController
{
    public function __construct(private readonly CategoryService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        // `all=1` devuelve la lista completa (para selects del frontend).
        if ($request->boolean('all')) {
            return $this->ok(CategoryResource::collection($this->service->all(['is_active' => 1])));
        }

        return $this->ok(CategoryResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        return $this->created(new CategoryResource($this->service->create($request->validated())), 'Categoría creada.');
    }

    public function show(int $category): JsonResponse
    {
        return $this->ok(new CategoryResource($this->service->find($category, ['parent'])));
    }

    public function update(CategoryRequest $request, int $category): JsonResponse
    {
        return $this->ok(new CategoryResource($this->service->update($category, $request->validated())), 'Categoría actualizada.');
    }

    public function destroy(int $category): JsonResponse
    {
        $this->service->delete($category);

        return $this->noContent('Categoría eliminada.');
    }
}
