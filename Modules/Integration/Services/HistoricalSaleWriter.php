<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Settings\Models\Company;

/**
 * Escribe una venta ya ocurrida en la web (histórico) tal como fue: con su
 * fecha, sus pagos y su saldo, y SIN mover stock. El stock que la web tenía al
 * conectarse ya entró al sistema con esas ventas descontadas; moverlo otra vez
 * lo descuadraría.
 *
 * El código de la web (V-000001, W-000017) es el número del comprobante y la
 * referencia externa: importar dos veces devuelve la misma venta.
 */
class HistoricalSaleWriter
{
    /**
     * @param  array<string, mixed>  $data  reference, channel, customer_id, user_id, sold_at, notes, discount,
     *                                      status (completed|cancelled), cancelled_at?, cancel_reason?,
     *                                      items[]{product_id?, employee_id?, description, quantity, price, discount, subtotal},
     *                                      payments[]{method, amount, reference?, user_id?, paid_at?}
     */
    public function write(array $data): Sale
    {
        $existing = Sale::withTrashed()->where('external_reference', $data['reference'])->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($data): Sale {
            $company = Company::query()->first();
            $taxPercent = (float) ($company?->igv_percent ?? 18);
            $includeIgv = (bool) ($company?->prices_include_igv ?? true);

            $costs = Product::withTrashed()
                ->whereIn('id', array_filter(array_column($data['items'], 'product_id')))
                ->pluck('cost', 'id');

            $itemsTotal = round(array_sum(array_map(fn ($i) => (float) $i['subtotal'], $data['items'])), 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round(max($itemsTotal - $discount, 0), 2);
            [$base, $tax] = $this->splitTax($total, $taxPercent, $includeIgv);

            $paid = round(min(array_sum(array_map(fn ($p) => (float) $p['amount'], $data['payments'] ?? [])), $total), 2);
            $cancelled = ($data['status'] ?? 'completed') === 'cancelled';
            $soldAt = Carbon::parse($data['sold_at'] ?? now());

            $sale = Sale::create([
                'company_id' => $company?->id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'doc_type' => 'ticket',
                'channel' => $data['channel'],
                'external_reference' => $data['reference'],
                'full_number' => $data['reference'],
                'subtotal' => $base,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'tax_percent' => $taxPercent,
                'paid' => $paid,
                'change' => 0,
                'status' => $cancelled ? 'cancelled' : 'completed',
                'payment_status' => $cancelled ? 'cancelled' : Sale::paymentStatusFor($total, $paid),
                'notes' => $data['notes'] ?? null,
                'sold_at' => $soldAt,
                'cancelled_at' => $cancelled ? Carbon::parse($data['cancelled_at'] ?? $soldAt) : null,
                'cancel_reason' => $cancelled ? ($data['cancel_reason'] ?? 'Anulada en la web') : null,
            ]);
            $sale->forceFill(['created_at' => $soldAt])->saveQuietly();

            foreach ($data['items'] as $item) {
                [, $lineTax] = $this->splitTax((float) $item['subtotal'], $taxPercent, $includeIgv);
                $sale->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'employee_id' => $item['employee_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost' => (float) ($costs[$item['product_id'] ?? 0] ?? 0),
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $lineTax,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'user_id' => $payment['user_id'] ?? null,
                    'paid_at' => Carbon::parse($payment['paid_at'] ?? $soldAt),
                ]);
            }

            return $sale;
        });
    }

    /** @return array{0: float, 1: float} [base, IGV] */
    private function splitTax(float $total, float $taxPercent, bool $includeIgv): array
    {
        if ($taxPercent <= 0) {
            return [$total, 0.0];
        }

        if ($includeIgv) {
            $base = round($total / (1 + $taxPercent / 100), 2);

            return [$base, round($total - $base, 2)];
        }

        return [$total, round($total * $taxPercent / 100, 2)];
    }
}
