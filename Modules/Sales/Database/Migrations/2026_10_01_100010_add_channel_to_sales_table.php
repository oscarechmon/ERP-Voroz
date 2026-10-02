<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde viene la venta: el POS o la tienda online de la web. La referencia
 * externa es el código del pedido web (W-000123); al ser única, un mismo pedido
 * no puede registrarse dos veces aunque la web reintente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('channel', 20)->default('pos')->after('doc_type')->index();
            $table->string('external_reference', 50)->nullable()->unique()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['external_reference']);
            $table->dropIndex(['channel']);
            $table->dropColumn(['channel', 'external_reference']);
        });
    }
};
