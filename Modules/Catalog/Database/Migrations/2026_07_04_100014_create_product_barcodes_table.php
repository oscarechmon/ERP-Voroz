<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de barras adicionales por producto (un producto puede tener varios:
 * presentaciones, empaques, EAN + interno). El escaneo busca en esta tabla y en
 * `products.barcode`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 50)->index();
            $table->string('type', 20)->default('CODE128'); // EAN13/CODE128/QR
            $table->timestamps();

            $table->unique(['product_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
