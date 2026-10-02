<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Requests\ConsumptionRequest;
use Modules\Integration\Http\Requests\ImportProductRequest;
use Modules\Integration\Http\Requests\WebSaleRequest;
use Modules\Integration\Services\CatalogFeed;
use Modules\Integration\Services\ConsumptionService;
use Modules\Integration\Services\ProductImporter;
use Modules\Integration\Services\WebSaleService;
use Modules\Sales\Models\Sale;

/**
 * API que usa la web (sin_excusas): leer el catálogo y registrar en el sistema
 * lo que mueve stock desde allá. Las operaciones que tocan stock devuelven el
 * stock resultante de sus productos para que la web actualice su copia.
 */
class IntegrationController extends ApiController
{
    public function __construct(private readonly CatalogFeed $feed) {}

    /** Catálogo completo, o solo los productos de `ids[]`. */
    public function catalog(Request $request): JsonResponse
    {
        $ids = $request->has('ids') ? array_map('intval', (array) $request->input('ids')) : null;

        return $this->ok($this->feed->items($ids));
    }

    public function storeProduct(ImportProductRequest $request, ProductImporter $importer): JsonResponse
    {
        $product = $importer->import($request->validated());

        return $this->ok($this->feed->items([$product->id])[0], "Producto {$product->code} listo.");
    }

    public function storeSale(WebSaleRequest $request, WebSaleService $sales): JsonResponse
    {
        $sale = $sales->register($request->validated());

        return $this->ok($this->saleResult($sale), "Venta {$sale->full_number} registrada.");
    }

    public function cancelSale(Request $request, string $reference, WebSaleService $sales): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $sale = $sales->cancel($reference, $data['reason'] ?? null);

        return $this->ok($this->saleResult($sale), "Venta {$sale->full_number} anulada.");
    }

    public function storeConsumption(ConsumptionRequest $request, ConsumptionService $consumptions): JsonResponse
    {
        $data = $request->validated();

        $consumptions->register($data['reference'], $data['items'], $data['notes'] ?? null);

        return $this->ok(['stock' => $this->feed->stock(array_column($data['items'], 'product_id'))], 'Consumo registrado.');
    }

    /** @return array<string, mixed> */
    private function saleResult(Sale $sale): array
    {
        $productIds = $sale->items()->whereNotNull('product_id')->pluck('product_id')->all();

        return [
            'sale' => [
                'id' => $sale->id,
                'full_number' => $sale->full_number,
                'total' => (float) $sale->total,
                'status' => $sale->status,
            ],
            'stock' => $this->feed->stock($productIds),
        ];
    }
}
