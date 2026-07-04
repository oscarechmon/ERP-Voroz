<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sesiones de caja (apertura → cierre con arqueo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('opening_amount', 14, 2)->default(0);
            $table->decimal('cash_sales', 14, 2)->default(0);   // Ventas en efectivo del turno
            $table->decimal('income', 14, 2)->default(0);       // Ingresos manuales
            $table->decimal('expense', 14, 2)->default(0);      // Egresos manuales
            $table->decimal('expected_amount', 14, 2)->nullable(); // Esperado en caja
            $table->decimal('counted_amount', 14, 2)->nullable();  // Contado en el arqueo
            $table->decimal('difference', 14, 2)->nullable();      // Sobrante/faltante

            $table->string('status', 10)->default('open');      // open/closed
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
