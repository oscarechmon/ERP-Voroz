<?php

declare(strict_types=1);

namespace Modules\Purchases\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Purchases\Http\Requests\StorePurchaseRequest;
use Modules\Purchases\Http\Resources\PurchaseResource;
use Modules\Purchases\Repositories\PurchaseRepository;
use Modules\Purchases\Services\PurchaseService;

class PurchaseController extends ApiController
{
    public function __construct(
        private readonly PurchaseService $service,
        private readonly PurchaseRepository $repository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $purchases = $this->repository->paginate($request->all());

        return $this->ok(PurchaseResource::collection($purchases)->response()->getData(true));
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->service->register($request->validated());

        return $this->created(new PurchaseResource($purchase), "Compra {$purchase->number} registrada. Stock actualizado.");
    }

    public function show(int $purchase): JsonResponse
    {
        $model = $this->repository->findOrFail($purchase, ['items', 'supplier', 'user']);

        return $this->ok(new PurchaseResource($model));
    }
}
