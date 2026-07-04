<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pagos de una venta (soporta pago mixto: varios métodos por venta). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);   // efectivo/yape/plin/transferencia/tarjeta
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable(); // Nº operación (Yape/tarjeta)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
