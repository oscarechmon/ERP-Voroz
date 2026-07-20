<?php

declare(strict_types=1);

namespace Modules\Purchases\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Services\StockService;
use Modules\Purchases\Models\Purchase;
use Modules\Settings\Models\Company;

/**
 * Lógica de negocio de compras. Al registrar una compra, repone el stock del
 * almacén y recalcula el costo promedio ponderado de cada producto (vía
 * StockService), y actualiza el costo de referencia del producto.
 *
 * Editar o eliminar una compra revierte primero su efecto en inventario
 * (salidas de stock por la cantidad comprada) y, en el caso de la edición,
 * vuelve a aplicar el nuevo detalle. Todo el kardex queda registrado.
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
            $warehouseId = (int) ($data['warehouse_id'] ?? 0);

            [$lines, $base] = $this->buildLines($items);
            $totals = $this->totals($base, $data, $company);

            $purchase = Purchase::create([
                'company_id' => $company?->id,
                'branch_id' => $user?->branch_id,
                'warehouse_id' => $warehouseId ?: null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $user?->id,
                'number' => $this->nextNumber(),
                'supplier_doc' => $data['supplier_doc'] ?? null,
                'doc_type' => $data['doc_type'] ?? 'factura',
                'subtotal' => $totals['base'],
                'tax' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'tax_percent' => $totals['tax_percent'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'purchased_at' => now(),
            ]);

            $this->applyItems($purchase, $lines, $warehouseId);

            return $purchase->load(['items', 'supplier', 'user']);
        });
    }

    /**
     * Edita una compra: revierte el stock/costo de la compra original y vuelve
     * a aplicar el nuevo detalle, recalculando el costo promedio ponderado.
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new BusinessException('La compra no tiene productos.');
        }

        return DB::transaction(function () use ($purchase, $data, $items): Purchase {
            $company = Company::find($purchase->company_id) ?? Company::first();

            // 1) Revierte el efecto en inventario de la compra original.
            $this->reverseStock($purchase);
            $purchase->items()->delete();

            // 2) Recalcula totales y aplica el nuevo detalle.
            $warehouseId = (int) ($data['warehouse_id'] ?? $purchase->warehouse_id ?? 0);
            [$lines, $base] = $this->buildLines($items);
            $totals = $this->totals($base, $data, $company);

            $purchase->update([
                'warehouse_id' => $warehouseId ?: null,
                'supplier_id' => $data['supplier_id'] ?? $purchase->supplier_id,
                'supplier_doc' => $data['supplier_doc'] ?? null,
                'doc_type' => $data['doc_type'] ?? $purchase->doc_type,
                'subtotal' => $totals['base'],
                'tax' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'tax_percent' => $totals['tax_percent'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyItems($purchase, $lines, $warehouseId);

            return $purchase->fresh(['items', 'supplier', 'user']);
        });
    }

    /**
     * Elimina una compra: revierte su efecto en inventario y hace soft-delete.
     */
    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $this->reverseStock($purchase);
            $purchase->delete();
        });
    }

    /**
     * Valida el detalle recibido, resuelve los productos y devuelve
     * [líneas normalizadas, base sin descuento].
     *
     * @return array{0: list<array{product: Product, quantity: float, cost: float, subtotal: float}>, 1: float}
     */
    private function buildLines(array $items): array
    {
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

        return [$lines, $base];
    }

    /**
     * Calcula base, IGV y total. El IGV se aplica sólo si «apply_igv» es true
     * (proveedores sin IGV / régimen RUS → tax_percent 0).
     *
     * @return array{base: float, tax: float, discount: float, total: float, tax_percent: float}
     */
    private function totals(float $base, array $data, ?Company $company): array
    {
        $applyIgv = (bool) ($data['apply_igv'] ?? true);
        $taxPercent = $applyIgv ? (float) ($company?->igv_percent ?? 18) : 0.0;

        $discount = (float) ($data['discount'] ?? 0);
        $base = round($base - $discount, 2);
        $tax = round($base * $taxPercent / 100, 2);
        $total = round($base + $tax, 2);

        return ['base' => $base, 'tax' => $tax, 'discount' => $discount, 'total' => $total, 'tax_percent' => $taxPercent];
    }

    /**
     * Crea las líneas de la compra y repone el stock (entrada con recálculo del
     * costo promedio ponderado), actualizando el costo de referencia del producto.
     *
     * @param  list<array{product: Product, quantity: float, cost: float, subtotal: float}>  $lines
     */
    private function applyItems(Purchase $purchase, array $lines, int $warehouseId): void
    {
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

                $this->syncProductCost($line['product'], $warehouseId, $line['cost']);
            }
        }
    }

    /**
     * Revierte el efecto en inventario de una compra: registra una salida por la
     * cantidad comprada de cada producto (permitiendo negativos, pues el stock
     * pudo haberse consumido) y reajusta el costo de referencia.
     */
    private function reverseStock(Purchase $purchase): void
    {
        $warehouseId = (int) $purchase->warehouse_id;
        if (! $warehouseId) {
            return;
        }

        foreach ($purchase->items()->get() as $item) {
            if (! $item->product_id) {
                continue;
            }

            $this->stock->exit(
                $item->product_id,
                $warehouseId,
                (float) $item->quantity,
                $purchase,
                "Reversa compra {$purchase->number}",
                true,
            );

            $product = Product::find($item->product_id);
            if ($product) {
                $this->syncProductCost($product, $warehouseId, (float) $item->cost);
            }
        }
    }

    /** Actualiza el costo de referencia del producto al promedio ponderado vigente. */
    private function syncProductCost(Product $product, int $warehouseId, float $fallbackCost): void
    {
        $avg = $this->stock->available($product->id, $warehouseId) > 0
            ? (float) Stock::where('product_id', $product->id)->where('warehouse_id', $warehouseId)->value('avg_cost')
            : $fallbackCost;

        $product->forceFill(['cost' => $avg])->saveQuietly();
    }

    /** Correlativo interno de compra (COMP-000001…). */
    private function nextNumber(): string
    {
        $last = Purchase::withTrashed()->where('number', 'like', 'COMP-%')->orderByDesc('id')->value('number');
        $n = $last ? ((int) substr($last, 5)) + 1 : 1;

        return 'COMP-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
