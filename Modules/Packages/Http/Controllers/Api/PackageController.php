<?php

declare(strict_types=1);

namespace Modules\Packages\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Packages\Http\Requests\PackageRequest;
use Modules\Packages\Http\Resources\PackageResource;
use Modules\Packages\Services\PackageService;

class PackageController extends ApiController
{
    public function __construct(private readonly PackageService $service) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(PackageResource::collection($this->service->all(['is_active' => 1, 'sort_by' => 'name', 'sort_dir' => 'asc'])));
        }

        return $this->ok(PackageResource::collection($this->service->list($request->all()))->response()->getData(true));
    }

    public function store(PackageRequest $request): JsonResponse
    {
        return $this->created(new PackageResource($this->service->create($request->validated())), 'Paquete creado.');
    }

    public function show(int $package): JsonResponse
    {
        return $this->ok(new PackageResource($this->service->find($package)));
    }

    public function update(PackageRequest $request, int $package): JsonResponse
    {
        return $this->ok(new PackageResource($this->service->update($package, $request->validated())), 'Paquete actualizado.');
    }

    public function destroy(int $package): JsonResponse
    {
        $this->service->delete($package);

        return $this->noContent('Paquete eliminado.');
    }
}
