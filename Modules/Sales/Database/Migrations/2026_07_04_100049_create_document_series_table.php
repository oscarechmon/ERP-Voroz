<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Series y correlativos de comprobantes por sucursal (estándar SUNAT: cada tipo
 * de documento tiene una serie y un número correlativo autoincremental).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('doc_type', 20);          // ticket/boleta/factura/cotizacion
            $table->string('series', 8);              // B001, F001, etc.
            $table->unsignedBigInteger('current_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'doc_type', 'series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
