<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos. Núcleo del catálogo: identificación (código interno, SKU, barras),
 * precios (costo, venta, mayorista, oferta), control de stock (min/max) y estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code', 50)->unique();          // Código interno
            $table->string('barcode', 50)->nullable()->index(); // EAN13/CODE128 principal
            $table->string('sku', 50)->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('qr_path')->nullable();

            $table->decimal('cost', 12, 2)->default(0);            // Costo
            $table->decimal('price', 12, 2)->default(0);           // Precio venta
            $table->decimal('wholesale_price', 12, 2)->nullable(); // Mayorista
            $table->decimal('offer_price', 12, 2)->nullable();     // Oferta

            $table->decimal('stock_min', 12, 2)->default(0);
            $table->decimal('stock_max', 12, 2)->nullable();
            $table->boolean('track_stock')->default(true);
            $table->boolean('has_expiry')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
