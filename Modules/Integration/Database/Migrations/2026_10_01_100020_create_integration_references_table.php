<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operaciones de la web ya aplicadas (p. ej. el consumo de insumos de una
 * atención), para que un reintento no descuente el stock dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_references', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->string('kind', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_references');
    }
};
