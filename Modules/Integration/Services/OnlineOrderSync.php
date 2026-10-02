<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\OnlineOrders\Models\OnlineOrder;
use Modules\OnlineOrders\Models\OnlineOrderStatusHistory;
use Modules\Sales\Models\Sale;

/**
 * Recibe los pedidos de la tienda online tal como están en la web (datos,
 * líneas e historial) y los deja al día aquí.
 *
 * La web manda mientras el pedido se cobra (pendiente, rechazado, pagado);
 * desde que está pagado, el seguimiento es de aquí y lo que mande la web ya no
 * cambia su estado. Un pedido pagado genera su venta (canal web) una sola vez:
 * la de un pedido nuevo descuenta stock; la de uno histórico, no.
 */
class OnlineOrderSync
{
    public function __construct(
        private readonly WebSaleService $sales,
        private readonly HistoricalSaleWriter $history,
        private readonly CustomerResolver $customers,
    ) {}

    /** @param  array<string, mixed>  $data  Validado por OnlineOrderRequest. */
    public function upsert(array $data, bool $historical = false): OnlineOrder
    {
        return DB::transaction(function () use ($data, $historical): OnlineOrder {
            /** @var OnlineOrder $order */
            $order = OnlineOrder::where('code', $data['code'])->lockForUpdate()->first() ?? new OnlineOrder(['code' => $data['code']]);
            $customer = $this->customers->resolve($data['customer'] ?? []);

            $status = $order->exists && ! in_array($order->status, OnlineOrder::WEB_STATUSES, true)
                ? $order->status
                : $data['status'];

            $order->fill([
                'web_id' => $data['web_id'] ?? $order->web_id,
                'customer_id' => $customer?->id ?? $order->customer_id,
                'status' => $status,
                'fulfillment' => $data['fulfillment'],
                'customer_name' => $data['customer']['name'] ?? null,
                'customer_email' => $data['customer']['email'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'district' => $data['district'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $data['subtotal'],
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'total' => $data['total'],
                'gateway' => $data['gateway'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
                'ordered_at' => $data['ordered_at'] ?? $order->ordered_at ?? now(),
            ])->save();

            $known = Product::withTrashed()->whereIn('id', array_filter(array_column($data['items'], 'product_id')))->pluck('id')->all();

            $order->items()->delete();
            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => in_array((int) ($item['product_id'] ?? 0), $known, true) ? (int) $item['product_id'] : null,
                    'item_type' => $item['item_type'] ?? 'product',
                    'name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'] ?? round((float) $item['unit_price'] * (float) $item['quantity'], 2),
                ]);
            }

            // El historial de la web se reemplaza; el de aquí (seguimiento) se conserva.
            $order->histories()->where('source', OnlineOrderStatusHistory::SOURCE_WEB)->delete();
            foreach ($data['history'] ?? [] as $step) {
                $order->histories()->create([
                    'status' => $step['status'],
                    'note' => $step['note'] ?? null,
                    'internal' => (bool) ($step['internal'] ?? false),
                    'source' => OnlineOrderStatusHistory::SOURCE_WEB,
                    'user_name' => $step['user_name'] ?? null,
                    'happened_at' => Carbon::parse($step['happened_at']),
                ]);
            }

            $this->registerSale($order, $data, $historical);

            return $order->fresh(['items', 'histories', 'sale']);
        });
    }

    /**
     * Venta del pedido, una sola vez: la que ya existía con su código, o una
     * nueva si el pedido está cobrado (o se cobró y la web lo anuló).
     *
     * @param  array<string, mixed>  $data
     */
    private function registerSale(OnlineOrder $order, array $data, bool $historical): void
    {
        if ($order->sale_id) {
            return;
        }

        $sale = Sale::withTrashed()->where('external_reference', $order->code)->first();

        $paidThenCancelled = $order->status === OnlineOrder::CANCELLED && ! empty($data['paid_at']);
        if (! $sale && ($order->isPaid() || ($historical && $paidThenCancelled))) {
            $sale = $historical ? $this->writeHistorical($order, $data) : $this->sales->register([
                'reference' => $order->code,
                'customer' => $data['customer'] ?? [],
                'items' => array_map(fn (array $i) => [
                    'product_id' => $i['product_id'] ?? null,
                    'name' => $i['name'],
                    'quantity' => $i['quantity'],
                    'price' => $i['unit_price'],
                ], $data['items']),
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'payments' => [[
                    'method' => $data['gateway'] ?? 'izipay',
                    'amount' => $data['total'],
                    'reference' => $data['payment_reference'] ?? null,
                ]],
            ]);
        }

        if ($sale) {
            $order->forceFill(['sale_id' => $sale->id])->save();
        }
    }

    /** @param  array<string, mixed>  $data */
    private function writeHistorical(OnlineOrder $order, array $data): Sale
    {
        $items = $order->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'description' => $i->name,
            'quantity' => (float) $i->quantity,
            'price' => (float) $i->unit_price,
            'subtotal' => (float) $i->subtotal,
        ])->all();

        if ((float) $order->delivery_fee > 0) {
            $items[] = ['product_id' => null, 'description' => 'Delivery', 'quantity' => 1, 'price' => (float) $order->delivery_fee, 'subtotal' => (float) $order->delivery_fee];
        }

        return $this->history->write([
            'reference' => $order->code,
            'channel' => Sale::CHANNEL_WEB,
            'customer_id' => $order->customer_id,
            'sold_at' => $data['paid_at'] ?? $data['ordered_at'] ?? now(),
            'notes' => "Pedido web {$order->code}",
            'status' => $order->status === OnlineOrder::CANCELLED ? 'cancelled' : 'completed',
            'cancel_reason' => 'Pedido anulado en la web',
            'items' => $items,
            'payments' => [[
                'method' => $data['gateway'] ?? 'izipay',
                'amount' => (float) $order->total,
                'reference' => $order->payment_reference,
                'paid_at' => $data['paid_at'] ?? null,
            ]],
        ]);
    }
}
