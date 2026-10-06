<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\SaleService;

/**
 * Pedidos pagados en la tienda online: se registran como ventas del sistema
 * (canal web) para que descuenten stock y aparezcan en ventas y reportes.
 *
 * El código del pedido (W-000123) es la referencia externa de la venta. La web
 * puede reintentar sin miedo: un pedido ya registrado devuelve su venta.
 */
class WebSaleService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly IntegrationWarehouse $warehouse,
        private readonly CustomerResolver $customers,
    ) {}

    /** @param  array<string, mixed>  $data  Validado por WebSaleRequest. */
    public function register(array $data): Sale
    {
        $existing = Sale::withTrashed()->where('external_reference', $data['reference'])->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($data): Sale {
            $known = Product::whereIn('id', array_filter(array_column($data['items'], 'product_id')))
                ->pluck('id')
                ->all();

            $items = array_map(
                fn (array $row): array => in_array((int) ($row['product_id'] ?? 0), $known, true)
                    ? ['product_id' => (int) $row['product_id'], 'quantity' => $row['quantity'], 'price' => $row['price']]
                    // Ya no está en el catálogo: el cliente pagó igual, así que entra como concepto.
                    : ['description' => $row['name'], 'quantity' => $row['quantity'], 'price' => $row['price']],
                $data['items'],
            );

            $deliveryFee = (float) ($data['delivery_fee'] ?? 0);
            if ($deliveryFee > 0) {
                $items[] = ['description' => $data['delivery_label'] ?? 'Delivery', 'quantity' => 1, 'price' => $deliveryFee];
            }

            $sale = $this->sales->checkout([
                'doc_type' => 'ticket',
                'customer_id' => $this->customerId($data['customer'] ?? []),
                'warehouse_id' => $this->warehouse->id(),
                'items' => $items,
                'payments' => $data['payments'],
                'notes' => "Pedido web {$data['reference']}",
            ]);

            $sale->update(['channel' => Sale::CHANNEL_WEB, 'external_reference' => $data['reference']]);

            return $sale;
        });
    }

    /** Anulación del pedido en la web: anula la venta y devuelve el stock. */
    public function cancel(string $reference, ?string $reason = null): Sale
    {
        $sale = Sale::where('external_reference', $reference)->first()
            ?? throw new BusinessException("No hay ninguna venta del pedido {$reference}.", 404);

        // La web puede reintentar: anular dos veces no es un error.
        if ($sale->status === 'cancelled') {
            return $sale;
        }

        return $this->sales->cancel($sale, $reason ?: "Pedido web {$reference} anulado en la web");
    }

    /**
     * Busca al cliente (por su enlace con la web, documento o correo); si no
     * existe, lo crea con los datos del pedido.
     *
     * @param  array<string, mixed>  $customer
     */
    private function customerId(array $customer): ?int
    {
        return $this->customers->resolve($customer)?->id;
    }
}
