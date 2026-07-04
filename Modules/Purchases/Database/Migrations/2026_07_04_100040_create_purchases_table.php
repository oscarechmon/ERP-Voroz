<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cabecera de compra a proveedor. Al completarse, repone stock y costo promedio. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number', 30)->nullable()->index();   // Correlativo interno
            $table->string('supplier_doc', 30)->nullable();       // Nº factura del proveedor
            $table->string('doc_type', 20)->default('factura');   // factura/boleta/nota

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(18);

            $table->string('status', 20)->default('completed');   // completed/cancelled
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
