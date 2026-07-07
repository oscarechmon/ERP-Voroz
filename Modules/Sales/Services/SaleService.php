<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Services\StockService;
use Modules\Sales\Models\DocumentSeries;
use Modules\Sales\Models\Sale;
use Modules\Settings\Models\Company;

/**
 * Lógica de negocio de ventas (checkout del POS).
 *
 * Todo el cálculo de importes se realiza en el servidor a partir de los productos
 * reales (nunca se confía en los totales enviados por el cliente). El proceso es
 * atómico: correlativo con bloqueo, creación de cabecera/detalle/pagos y descuento
 * de stock, todo dentro de una transacción.
 */
class SaleService
{
    /** Prefijos de serie por tipo de documento. */
    private const SERIES = [
        'ticket' => 'T001',
        'boleta' => 'B001',
        'factura' => 'F001',
        'cotizacion' => 'C001',
    ];

    public function __construct(private readonly StockService $stock) {}

    /**
     * Procesa una venta completa.
     *
     * @param  array  $data  doc_type, customer_id, warehouse_id, notes, discount, items[], payments[]
     */
    public function checkout(array $data): Sale
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new BusinessException('La venta no tiene productos.');
        }

        return DB::transaction(function () use ($data, $items): Sale {
            $user = auth()->user();
            $company = Company::find($user?->company_id) ?? Company::first();
            $taxPercent = (float) ($company?->igv_percent ?? 18);
            $pricesIncludeIgv = (bool) ($company?->prices_include_igv ?? true);

            $docType = $data['doc_type'] ?? 'ticket';
            $isQuotation = $docType === 'cotizacion';
            $warehouseId = (int) ($data['warehouse_id'] ?? 0);

            // 1) Construye las líneas a partir de los productos reales (precio/costo del sistema).
            $productIds = array_column($items, 'product_id');
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $lines = [];
            $itemsTotal = 0.0; // Suma de líneas (con IGV incluido si aplica)
            foreach ($items as $row) {
                $product = $products->get($row['product_id']);
                if (! $product) {
                    throw new BusinessException("Producto {$row['product_id']} no encontrado.");
                }

                $qty = (float) $row['quantity'];
                if ($qty <= 0) {
                    throw new BusinessException("Cantidad inválida para «{$product->name}».");
                }

                // El precio puede editarse en el POS según permiso; si no viene, usa el del producto.
                $price = isset($row['price']) ? (float) $row['price'] : (float) $product->price;
                $lineDiscount = (float) ($row['discount'] ?? 0);
                $lineTotal = round(($price * $qty) - $lineDiscount, 2);

                $lines[] = [
                    'product' => $product,
                    'description' => $product->name,
                    'quantity' => $qty,
                    'price' => $price,
                    'cost' => (float) $product->cost,
                    'discount' => $lineDiscount,
                    'subtotal' => $lineTotal,
                ];
                $itemsTotal += $lineTotal;
            }

            $globalDiscount = (float) ($data['discount'] ?? 0);
            $total = round($itemsTotal - $globalDiscount, 2);
            if ($total < 0) {
                throw new BusinessException('El descuento no puede superar el total.');
            }

            // 2) Descompone base imponible e IGV.
            [$base, $tax] = $this->splitTax($total, $taxPercent, $pricesIncludeIgv);

            // 3) Valida pagos (salvo cotización).
            $payments = $data['payments'] ?? [];
            $paid = round(array_sum(array_map(static fn ($p) => (float) $p['amount'], $payments)), 2);
            if (! $isQuotation) {
                if ($paid + 0.001 < $total) {
                    throw new BusinessException("El pago (S/{$paid}) es menor al total (S/{$total}).");
                }
            }
            $change = $isQuotation ? 0.0 : round(max($paid - $total, 0), 2);

            // 4) Correlativo (con bloqueo) y cabecera.
            [$series, $number, $fullNumber] = $this->nextDocumentNumber($docType, $user?->branch_id);

            $sale = Sale::create([
                'company_id' => $company?->id,
                'branch_id' => $user?->branch_id,
                'warehouse_id' => $warehouseId ?: null,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $user?->id,
                'doc_type' => $docType,
                'series' => $series,
                'number' => $number,
                'full_number' => $fullNumber,
                'subtotal' => $base,
                'tax' => $tax,
                'discount' => $globalDiscount,
                'total' => $total,
                'tax_percent' => $taxPercent,
                'paid' => $isQuotation ? 0 : $paid,
                'change' => $change,
                'status' => $isQuotation ? 'quotation' : 'completed',
                'payment_status' => $isQuotation ? 'pending' : 'paid',
                'notes' => $data['notes'] ?? null,
                'sold_at' => now(),
            ]);

            // 5) Detalle + descuento de stock (las cotizaciones no afectan inventario).
            foreach ($lines as $line) {
                [$lineBase, $lineTax] = $this->splitTax($line['subtotal'], $taxPercent, $pricesIncludeIgv);

                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                    'cost' => $line['cost'],
                    'discount' => $line['discount'],
                    'tax' => $lineTax,
                    'subtotal' => $line['subtotal'],
                ]);

                if (! $isQuotation && $warehouseId && $line['product']->track_stock) {
                    $this->stock->exit(
                        $line['product']->id,
                        $warehouseId,
                        $line['quantity'],
                        $sale,
                        "Venta {$fullNumber}",
                        allowNegative: true, // El POS no bloquea la venta; queda registrado el faltante.
                    );
                }
            }

            // 6) Pagos.
            foreach ($payments as $payment) {
                if ((float) $payment['amount'] <= 0) {
                    continue;
                }
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => (float) $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            return $sale->load(['items', 'payments', 'customer', 'user']);
        });
    }

    /**
     * Anula una venta ya emitida: la marca como anulada y devuelve el stock de
     * cada producto al almacén de origen. La operación es atómica y queda
     * registrada en auditoría (quién y cuándo la anuló) por el trait Auditable.
     */
    public function cancel(Sale $sale, ?string $reason = null): Sale
    {
        if ($sale->status === 'cancelled') {
            throw new BusinessException('La venta ya se encuentra anulada.');
        }
        if ($sale->status === 'quotation') {
            throw new BusinessException('Una cotización no puede anularse.');
        }

        return DB::transaction(function () use ($sale, $reason): Sale {
            $sale->loadMissing('items');

            // Reingresa al inventario lo vendido (sólo si la venta afectó un almacén).
            if ($sale->warehouse_id) {
                foreach ($sale->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->track_stock) {
                        $this->stock->entry(
                            $product->id,
                            (int) $sale->warehouse_id,
                            (float) $item->quantity,
                            0, // No altera el costo promedio (reingreso por anulación).
                            $sale,
                            "Anulación venta {$sale->full_number}",
                        );
                    }
                }
            }

            $sale->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);

            return $sale->fresh(['items', 'payments', 'customer', 'user', 'canceller']);
        });
    }

    /** Descompone un total en base imponible e IGV según si el precio incluye IGV. */
    private function splitTax(float $total, float $taxPercent, bool $pricesIncludeIgv): array
    {
        if ($taxPercent <= 0) {
            return [$total, 0.0];
        }

        if ($pricesIncludeIgv) {
            $base = round($total / (1 + $taxPercent / 100), 2);

            return [$base, round($total - $base, 2)];
        }

        $tax = round($total * $taxPercent / 100, 2);

        return [$total, $tax];
    }

    /**
     * Obtiene el siguiente correlativo del comprobante, bloqueando la fila de la
     * serie para evitar números duplicados ante ventas concurrentes.
     *
     * @return array{0:string,1:int,2:string} [serie, número, número completo]
     */
    private function nextDocumentNumber(string $docType, ?int $branchId): array
    {
        $seriesCode = self::SERIES[$docType] ?? 'T001';

        $series = DocumentSeries::where('doc_type', $docType)
            ->where('series', $seriesCode)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->lockForUpdate()
            ->first();

        if (! $series) {
            $series = DocumentSeries::create([
                'branch_id' => $branchId,
                'doc_type' => $docType,
                'series' => $seriesCode,
                'current_number' => 0,
            ]);
        }

        $number = $series->current_number + 1;
        $series->update(['current_number' => $number]);

        $full = $seriesCode.'-'.str_pad((string) $number, 8, '0', STR_PAD_LEFT);

        return [$seriesCode, $number, $full];
    }
}
