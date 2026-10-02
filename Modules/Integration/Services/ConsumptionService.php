<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Integration\Models\IntegrationReference;
use Modules\Inventory\Services\StockService;

/**
 * Insumos gastados en una atención de la web (agujas, gasas…): salen del stock
 * del sistema con su línea de kardex.
 *
 * A diferencia de una venta, aquí la falta de stock sí detiene la operación:
 * la web aún no confirmó la atención y puede avisar al usuario.
 */
class ConsumptionService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly IntegrationWarehouse $warehouse,
    ) {}

    /** @param  list<array{product_id:int, quantity:float}>  $items */
    public function register(string $reference, array $items, ?string $notes = null): void
    {
        // La web puede reintentar: una misma atención se descuenta una sola vez.
        if (IntegrationReference::where('reference', $reference)->exists()) {
            return;
        }

        DB::transaction(function () use ($reference, $items, $notes): void {
            IntegrationReference::create(['reference' => $reference, 'kind' => IntegrationReference::KIND_CONSUMPTION]);

            $warehouseId = $this->warehouse->id();

            foreach ($items as $item) {
                $product = Product::find($item['product_id'])
                    ?? throw new BusinessException("El insumo {$item['product_id']} no existe en el sistema.", 422);

                if (! $product->track_stock) {
                    continue;
                }

                $quantity = (float) $item['quantity'];
                $available = $this->stock->available($product->id, $warehouseId);

                if ($available < $quantity) {
                    throw new BusinessException(
                        "Stock insuficiente de «{$product->name}» en el sistema: hay {$available}, se necesitan {$quantity}.",
                        422,
                    );
                }

                $this->stock->exit($product->id, $warehouseId, $quantity, null, $notes ?: "Consumo {$reference}");
            }
        });
    }
}
