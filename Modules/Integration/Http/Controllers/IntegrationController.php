<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Http\Requests\ConsumptionRequest;
use Modules\Integration\Http\Requests\ImportProductRequest;
use Modules\Catalog\Models\Product;
use Modules\Integration\Http\Requests\OnlineOrderRequest;
use Modules\Integration\Http\Requests\WebDetailsRequest;
use Modules\Integration\Http\Requests\WebSaleRequest;
use Modules\Integration\Services\CatalogFeed;
use Modules\Integration\Services\ConsumptionService;
use Modules\Integration\Services\CustomerResolver;
use Modules\Integration\Services\OnlineOrderSync;
use Modules\Integration\Services\ProductImporter;
use Modules\Integration\Services\WebDetailsService;
use Modules\Integration\Services\WebImporter;
use Modules\Integration\Services\WebSaleService;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Models\OnlineOrderStatusHistory;
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

    /**
     * Cliente de la tienda online (al registrarse o actualizar sus datos). Lo
     * enlaza con su ficha de aquí, o la crea; devuelve el id para la web.
     */
    public function storeCustomer(Request $request, CustomerResolver $customers): JsonResponse
    {
        $data = $request->validate([
            'web_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer = $customers->resolve($data, update: true);

        return $this->ok(['id' => $customer?->id, 'code' => $customer?->code], 'Cliente enlazado.');
    }

    /** Pedido de la tienda tal como está en la web (al crearse, cobrarse o fallar el cobro). */
    public function storeOrder(OnlineOrderRequest $request, OnlineOrderSync $orders): JsonResponse
    {
        $data = $request->validated();
        $order = $orders->upsert($data, (bool) ($data['historical'] ?? false));

        return $this->ok([
            'order' => ['id' => $order->id, 'code' => $order->code, 'status' => $order->status],
            'sale' => $order->sale ? ['id' => $order->sale->id, 'full_number' => $order->sale->full_number] : null,
            'stock' => $this->feed->stock($order->items->whereNotNull('product_id')->pluck('product_id')->all()),
        ], "Pedido {$order->code} recibido.");
    }

    /**
     * Estado y seguimiento (hecho aquí) de unos pedidos: la web lo usa para
     * ponerse al día si no recibió algún aviso.
     */
    public function orderStatuses(Request $request): JsonResponse
    {
        $codes = array_slice(array_filter((array) $request->input('codes', [])), 0, 200);

        $orders = OnlineOrder::with(['histories' => fn ($q) => $q->where('source', OnlineOrderStatusHistory::SOURCE_SISTEMA)])
            ->whereIn('code', $codes)
            ->get();

        return $this->ok($orders->map(fn (OnlineOrder $o) => [
            'code' => $o->code,
            'status' => $o->status,
            'history' => $o->histories->map(fn ($h) => [
                'status' => $h->status,
                'note' => $h->note,
                'user_name' => $h->user_name,
                'happened_at' => $h->happened_at?->toIso8601String(),
            ])->values(),
        ])->values());
    }

    /**
     * Lo que la web mostraba de un ítem (imagen, descripción, si se publicaba,
     * duración): desde aquí pasa a administrarse en el sistema.
     */
    public function storeWebDetails(WebDetailsRequest $request, int $product, WebDetailsService $details): JsonResponse
    {
        $model = $details->apply(Product::findOrFail($product), $request->safe()->except('image'), $request->file('image'));

        return $this->ok($this->feed->items([$model->id])[0], "Ficha web de {$model->code} recibida.");
    }

    /** Descripción de una categoría que la web tenía (solo si aquí no hay una). */
    public function describeCategory(Request $request, WebDetailsService $details): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        return $this->ok(['updated' => $details->describeCategory($data['name'], $data['description'])]);
    }

    /** Importación del historial de la web, por tipo y por tandas. */
    public function import(Request $request, string $kind, WebImporter $importer): JsonResponse
    {
        $request->validate([
            'records' => ['present', 'array', 'max:500'],
            'records.*.id' => ['required', 'integer'],
        ]);

        // Cada tipo trae su forma; validated() solo dejaría el id de cada registro.
        return $this->ok($importer->import($kind, (array) $request->input('records')), 'Importación aplicada.');
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
