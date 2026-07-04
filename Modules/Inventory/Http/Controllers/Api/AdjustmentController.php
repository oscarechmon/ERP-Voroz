<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Http\Requests\AdjustmentRequest;
use Modules\Inventory\Http\Resources\MovementResource;
use Modules\Inventory\Services\StockService;

/** Ajustes manuales de inventario (conteo físico, mermas, correcciones). */
class AdjustmentController extends ApiController
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function store(AdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $movement = $this->stock->adjust(
            (int) $data['product_id'],
            (int) $data['warehouse_id'],
            (float) $data['quantity'],
            $data['notes'] ?? null,
        );

        return $this->created(new MovementResource($movement), 'Inventario ajustado correctamente.');
    }
}
