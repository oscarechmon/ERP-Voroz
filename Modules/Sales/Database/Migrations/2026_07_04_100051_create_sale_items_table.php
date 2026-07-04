<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Detalle de la venta. Guarda snapshot de nombre/precio y costo para utilidad. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');            // Snapshot del nombre
            $table->decimal('quantity', 14, 2);
            $table->decimal('price', 14, 2);          // Precio unitario (con IGV)
            $table->decimal('cost', 14, 2)->default(0); // Costo unit. para utilidad
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);       // Total de la línea
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
