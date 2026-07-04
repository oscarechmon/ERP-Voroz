<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cabecera de venta / comprobante. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('doc_type', 20)->default('ticket'); // ticket/boleta/factura/cotizacion
            $table->string('series', 8)->nullable();
            $table->unsignedBigInteger('number')->nullable();
            $table->string('full_number', 30)->nullable()->index();  // B001-00000123

            $table->decimal('subtotal', 14, 2)->default(0);  // Base imponible (sin IGV)
            $table->decimal('tax', 14, 2)->default(0);        // IGV
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(18);
            $table->decimal('paid', 14, 2)->default(0);
            $table->decimal('change', 14, 2)->default(0);     // Vuelto

            $table->string('status', 20)->default('completed'); // completed/cancelled/quotation
            $table->string('payment_status', 20)->default('paid'); // paid/pending
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'sold_at']);
            $table->index('doc_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
