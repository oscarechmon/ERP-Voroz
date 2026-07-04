<?php

declare(strict_types=1);

namespace Modules\Purchases\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Services\StockService;
use Modules\Purchases\Models\Purchase;
use Modules\Settings\Models\Company;

/**
 * Lógica de negocio de compras. Al registrar una compra, repone el stock del
 * almacén y recalcula el costo promedio ponderado de cada producto (vía
 * StockService), y actualiza el costo de referencia del producto.
 */
class PurchaseService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function register(array $data): Purchase
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new BusinessException('La compra no tiene productos.');
        }

        return DB::transaction(function () use ($data, $items): Purchase {
            $user = auth()->user();
            $company = Company::find($user?->company_id) ?? Company::first();
            $taxPercent = (float) ($company?->igv_percent ?? 18);
            $warehouseId = (int) ($data['warehouse_id'] ?? 0);

            $products = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            $base = 0.0;
            $lines = [];
            foreach ($items as $row) {
                $product = $products->get($row['product_id']);
                if (! $product) {
                    throw new BusinessException("Producto {$row['product_id']} no encontrado.");
                }
                $qty = (float) $row['quantity'];
                $cost = (float) $row['cost'];
                if ($qty <= 0 || $cost < 0) {
                    throw new BusinessException("Datos inválidos para «{$product->name}».");
                }
                $lineTotal = round($qty * $cost, 2);
                $lines[] = ['product' => $product, 'quantity' => $qty, 'cost' => $cost, 'subtotal' => $lineTotal];
                $base += $lineTotal;
            }

            $discount = (float) ($data['discount'] ?? 0);
            $base = round($base - $discount, 2);
            $tax = round($base * $taxPercent / 100, 2);
            $total = round($base + $tax, 2);

            $purchase = Purchase::create([
                'company_id' => $company?->id,
                'branch_id' => $user?->branch_id,
                'warehouse_id' => $warehouseId ?: null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $user?->id,
                'number' => $this->nextNumber(),
                'supplier_doc' => $data['supplier_doc'] ?? null,
                'doc_type' => $data['doc_type'] ?? 'factura',
                'subtotal' => $base,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'tax_percent' => $taxPercent,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'purchased_at' => now(),
            ]);

            foreach ($lines as $line) {
                $purchase->items()->create([
                    'product_id' => $line['product']->id,
                    'description' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'cost' => $line['cost'],
                    'subtotal' => $line['subtotal'],
                ]);

                if ($warehouseId) {
                    // Entrada de stock con recálculo de costo promedio ponderado.
                    $this->stock->entry(
                        $line['product']->id,
                        $warehouseId,
                        $line['quantity'],
                        $line['cost'],
                        $purchase,
                        "Compra {$purchase->number}",
                    );

                    // Actualiza el costo de referencia del producto al nuevo promedio.
                    $avg = $this->stock->available($line['product']->id, $warehouseId) > 0
                        ? \Modules\Inventory\Models\Stock::where('product_id', $line['product']->id)
                            ->where('warehouse_id', $warehouseId)->value('avg_cost')
                        : $line['cost'];
                    $line['product']->forceFill(['cost' => $avg])->saveQuietly();
                }
            }

            return $purchase->load(['items', 'supplier', 'user']);
        });
    }

    /** Correlativo interno de compra (COMP-000001…). */
    private function nextNumber(): string
    {
        $last = Purchase::withTrashed()->where('number', 'like', 'COMP-%')->orderByDesc('id')->value('number');
        $n = $last ? ((int) substr($last, 5)) + 1 : 1;

        return 'COMP-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
