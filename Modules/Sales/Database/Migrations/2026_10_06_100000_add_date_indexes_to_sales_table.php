<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para lo que más se consulta de las ventas: el dashboard (ventas
 * completadas de hoy, del mes, del año) y el historial (las más recientes
 * primero). El índice que había empieza por company_id, que esas consultas no
 * filtran, así que la base recorría todas las ventas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['status', 'sold_at']);
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'sold_at']);
            $table->dropIndex(['sold_at']);
        });
    }
};
