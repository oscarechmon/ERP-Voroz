<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos de inventario = Kardex. Cada entrada/salida/ajuste queda registrada
 * con su cantidad, costo y el SALDO resultante (balance) para reconstruir el
 * kardex valorizado. `reference` es polimórfico (venta, compra, ajuste…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Tipo: in | out | adjustment | transfer_in | transfer_out
            $table->string('type', 20);
            $table->decimal('quantity', 14, 2);       // Con signo según entrada/salida
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('balance', 14, 2);        // Saldo tras el movimiento (kardex)

            $table->nullableMorphs('reference');       // reference_type + reference_id
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
