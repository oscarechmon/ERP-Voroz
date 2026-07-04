<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Inventory\Models\Stock;

/**
 * Servicio central de existencias. Aplica movimientos de forma atómica y segura
 * ante concurrencia (bloqueo de fila), recalcula el costo promedio ponderado en
 * las entradas y registra el kardex (movimiento con saldo resultante).
 *
 * Otros módulos (Ventas, Compras, Ajustes, Transferencias) dependen de este
 * servicio para no duplicar la lógica de stock (única fuente de verdad).
 */
class StockService
{
    /**
     * Registra una ENTRADA de stock (compra, devolución, transferencia entrante).
     * Recalcula el costo promedio ponderado.
     */
    public function entry(int $productId, int $warehouseId, float $quantity, float $unitCost = 0, ?Model $reference = null, ?string $notes = null, string $type = InventoryMovement::TYPE_IN): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new BusinessException('La cantidad de entrada debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $reference, $notes, $type): InventoryMovement {
            $stock = $this->lockStock($productId, $warehouseId);

            $oldQty = (float) $stock->quantity;
            $oldAvg = (float) $stock->avg_cost;
            $newQty = $oldQty + $quantity;

            // Costo promedio ponderado (sólo si hay costo de entrada informado).
            $newAvg = $unitCost > 0 && $newQty > 0
                ? round((($oldQty * $oldAvg) + ($quantity * $unitCost)) / $newQty, 4)
                : $oldAvg;

            $stock->update(['quantity' => $newQty, 'avg_cost' => $newAvg]);

            return $this->record($productId, $warehouseId, $type, $quantity, $unitCost ?: $oldAvg, $newQty, $reference, $notes);
        });
    }

    /**
     * Registra una SALIDA de stock (venta, merma, transferencia saliente).
     * Bloquea existencias negativas salvo que se permita explícitamente.
     */
    public function exit(int $productId, int $warehouseId, float $quantity, ?Model $reference = null, ?string $notes = null, bool $allowNegative = false, string $type = InventoryMovement::TYPE_OUT): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new BusinessException('La cantidad de salida debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reference, $notes, $allowNegative, $type): InventoryMovement {
            $stock = $this->lockStock($productId, $warehouseId);
            $newQty = (float) $stock->quantity - $quantity;

            if ($newQty < 0 && ! $allowNegative) {
                throw new BusinessException(
                    "Stock insuficiente. Disponible: {$stock->quantity}, solicitado: {$quantity}.",
                    422,
                );
            }

            $stock->update(['quantity' => $newQty]);

            // La salida se valoriza al costo promedio vigente.
            return $this->record($productId, $warehouseId, $type, -$quantity, (float) $stock->avg_cost, $newQty, $reference, $notes);
        });
    }

    /**
     * Ajuste manual: fija la cantidad a un valor absoluto y registra la diferencia
     * como movimiento de ajuste (positivo o negativo).
     */
    public function adjust(int $productId, int $warehouseId, float $newQuantity, ?string $notes = null): InventoryMovement
    {
        return DB::transaction(function () use ($productId, $warehouseId, $newQuantity, $notes): InventoryMovement {
            $stock = $this->lockStock($productId, $warehouseId);
            $diff = $newQuantity - (float) $stock->quantity;
            $stock->update(['quantity' => $newQuantity]);

            return $this->record(
                $productId,
                $warehouseId,
                InventoryMovement::TYPE_ADJUSTMENT,
                $diff,
                (float) $stock->avg_cost,
                $newQuantity,
                null,
                $notes ?? 'Ajuste manual de inventario',
            );
        });
    }

    /** Devuelve la cantidad disponible de un producto en un almacén. */
    public function available(int $productId, int $warehouseId): float
    {
        return (float) (Stock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    /** Obtiene (o crea) la fila de stock bloqueada para actualización. */
    private function lockStock(int $productId, int $warehouseId): Stock
    {
        Stock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'avg_cost' => 0],
        );

        return Stock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    /** Persiste la línea de kardex. */
    private function record(int $productId, int $warehouseId, string $type, float $quantity, float $cost, float $balance, ?Model $reference, ?string $notes): InventoryMovement
    {
        return InventoryMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => $quantity,
            'cost' => $cost,
            'balance' => $balance,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
        ]);
    }
}
