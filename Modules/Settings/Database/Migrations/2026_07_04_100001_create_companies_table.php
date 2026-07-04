<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas (soporte multiempresa desde el diseño). Cada registro transaccional
 * referencia una empresa para permitir escalar a multi-tenant en el futuro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('business_name');            // Razón social
            $table->string('trade_name')->nullable();   // Nombre comercial
            $table->string('ruc', 11)->unique();        // RUC (Perú)
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('currency', 3)->default('PEN');       // Moneda ISO
            $table->string('currency_symbol', 5)->default('S/');
            $table->decimal('igv_percent', 5, 2)->default(18.00); // IGV Perú
            $table->boolean('prices_include_igv')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
