<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada registro que llega de la web (cliente, venta, atención…) queda enlazado
 * con el suyo aquí: la referencia `web:customer:3` apunta al cliente creado.
 * Así importar dos veces no duplica nada, y lo que se importa después
 * (una venta) encuentra lo que se importó antes (su cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_references', function (Blueprint $table): void {
            $table->nullableMorphs('model');
        });
    }

    public function down(): void
    {
        Schema::table('integration_references', function (Blueprint $table): void {
            $table->dropMorphs('model');
        });
    }
};
